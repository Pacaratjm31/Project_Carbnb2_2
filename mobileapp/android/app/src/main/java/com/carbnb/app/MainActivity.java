package com.carbnb.app;

import android.Manifest;
import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.net.Uri;
import android.os.Bundle;
import android.provider.Settings;
import android.webkit.PermissionRequest;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.getcapacitor.BridgeActivity;
import com.getcapacitor.BridgeWebViewClient;

public class MainActivity extends BridgeActivity {

    // Any URL whose PATH contains one of these segments is blocked from
    // loading inside the mobile app. Admin tools are only reachable from
    // the desktop/browser version of the site, not from the APK.
    private static final String[] BLOCKED_PATH_SEGMENTS = {
        "/admin/",
        "/admin.php"
    };

    private static final int CAMERA_PERMISSION_REQUEST_CODE = 1001;

    // The live site (must match capacitor.config.json's server.url).
    private static final String REMOTE_URL = "https://carbnb.free.je";

    // Bundled locally inside the app (mobileapp/www/offline.html) - shown
    // instead of Android's plain default error page when there's no
    // connection. Design lives entirely in that HTML/CSS file, not here -
    // to restyle the offline screen, edit offline.html, not this file.
    private static final String OFFLINE_URL = "file:///android_asset/public/offline.html";

    // The website's offline.html page sends the browser here when the
    // user taps "Retry" - intercepted below in shouldOverrideUrlLoading.
    private static final String RETRY_URL = "carbnb://retry";

    // A local screen (mobileapp/www/camera_permission.html) shown before
    // face registration/verification, so camera access is requested
    // through a direct, native-controlled user tap - more reliable than
    // depending on the website's own JS to trigger it correctly every time.
    private static final String CAMERA_PERMISSION_URL = "file:///android_asset/public/camera_permission.html";
    private static final String REQUEST_CAMERA_URL = "carbnb://request-camera";
    private static final String SKIP_CAMERA_URL = "carbnb://skip-camera";

    // Any URL containing one of these segments shows the camera
    // permission screen first (if not already granted) before loading.
    private static final String[] FACE_AUTH_PATH_SEGMENTS = {
        "face_register.php",
        "face_verify.php"
    };

    // Remembers which page the user was actually trying to reach while
    // they're on the camera permission screen, so we can continue there
    // once they respond (whether Allow, Deny, or Not now).
    private String pendingFaceUrl;

    private static final String PREFS_NAME = "carbnb_prefs";
    private static final String PREF_CAMERA_ASKED_BEFORE = "camera_permission_asked_before";

    // Holds the website's pending camera request while we wait for the
    // user to respond to Android's own "Allow Carbnb to use the camera?"
    // system dialog. Once that resolves, we grant or deny this. This
    // applies to ANY page that requests the camera - including
    // auth/face_register.php and auth/face_verify.php - since it's not
    // tied to a specific URL, just to the browser's camera API itself.
    private PermissionRequest pendingWebPermissionRequest;

    // --- File chooser support (Valid ID / Proof of Billing / etc. uploads) ---
    private ValueCallback<Uri[]> filePathCallback;

    private final ActivityResultLauncher<Intent> fileChooserLauncher =
        registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
            if (filePathCallback == null) {
                return;
            }

            Uri[] results = null;

            if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                Intent data = result.getData();

                if (data.getClipData() != null) {
                    int count = data.getClipData().getItemCount();
                    results = new Uri[count];
                    for (int i = 0; i < count; i++) {
                        results[i] = data.getClipData().getItemAt(i).getUri();
                    }
                } else if (data.getData() != null) {
                    results = new Uri[]{ data.getData() };
                }
            }

            filePathCallback.onReceiveValue(results);
            filePathCallback = null;
        });

    private WebView webView;
    private ConnectivityManager.NetworkCallback networkCallback;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        webView = this.bridge.getWebView();

        registerNetworkCallback();

        // Capacitor already started loading REMOTE_URL as part of its own
        // bridge setup inside super.onCreate() above. If there's no
        // connection right now, immediately redirect to the local offline
        // page instead of waiting for that load to fail and show
        // Android's plain built-in error page.
        if (!isNetworkAvailable()) {
            webView.loadUrl(OFFLINE_URL);
        }

        webView.setWebViewClient(new BridgeWebViewClient(this.bridge) {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();

                if (url.startsWith(RETRY_URL)) {
                    if (isNetworkAvailable()) {
                        view.loadUrl(REMOTE_URL);
                    } else {
                        view.loadUrl(OFFLINE_URL);
                    }
                    return true;
                }

                if (url.startsWith(REQUEST_CAMERA_URL)) {
                    requestCameraPermissionNatively();
                    return true;
                }

                if (url.startsWith(SKIP_CAMERA_URL)) {
                    continueToPendingFaceUrl();
                    return true;
                }

                String path = request.getUrl().getPath();
                if (path != null) {
                    String lowerPath = path.toLowerCase();

                    for (String blocked : BLOCKED_PATH_SEGMENTS) {
                        if (lowerPath.contains(blocked)) {
                            Toast.makeText(
                                MainActivity.this,
                                "This section isn't available in the mobile app.",
                                Toast.LENGTH_SHORT
                            ).show();
                            return true;
                        }
                    }

                    boolean isFaceAuthPage = false;
                    for (String segment : FACE_AUTH_PATH_SEGMENTS) {
                        if (lowerPath.contains(segment)) {
                            isFaceAuthPage = true;
                            break;
                        }
                    }

                    if (isFaceAuthPage
                            && ContextCompat.checkSelfPermission(MainActivity.this, Manifest.permission.CAMERA)
                                != PackageManager.PERMISSION_GRANTED) {
                        pendingFaceUrl = url;
                        view.loadUrl(CAMERA_PERMISSION_URL);
                        return true;
                    }
                }

                return super.shouldOverrideUrlLoading(view, request);
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                super.onReceivedError(view, request, error);
                // Only react to failures on the main page itself, not on
                // a background image/script request failing. Also skip
                // if we're already showing (or asked to show) the offline
                // page, to avoid loadUrl loops.
                if (request.isForMainFrame() && !request.getUrl().toString().equals(OFFLINE_URL)) {
                    view.loadUrl(OFFLINE_URL);
                }
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onPermissionRequest(final PermissionRequest request) {
                runOnUiThread(() -> {
                    boolean wantsCamera = false;
                    for (String resource : request.getResources()) {
                        if (PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(resource)) {
                            wantsCamera = true;
                        }
                    }

                    if (!wantsCamera) {
                        request.deny();
                        return;
                    }

                    if (ContextCompat.checkSelfPermission(MainActivity.this, Manifest.permission.CAMERA)
                            == PackageManager.PERMISSION_GRANTED) {
                        request.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
                    } else {
                        pendingWebPermissionRequest = request;
                        ActivityCompat.requestPermissions(
                            MainActivity.this,
                            new String[]{Manifest.permission.CAMERA},
                            CAMERA_PERMISSION_REQUEST_CODE
                        );
                    }
                });
            }

            @Override
            public void onPermissionRequestCanceled(PermissionRequest request) {
                pendingWebPermissionRequest = null;
            }

            @Override
            public boolean onShowFileChooser(
                    WebView webView,
                    ValueCallback<Uri[]> filePathCallback,
                    FileChooserParams fileChooserParams
            ) {
                if (MainActivity.this.filePathCallback != null) {
                    MainActivity.this.filePathCallback.onReceiveValue(null);
                }
                MainActivity.this.filePathCallback = filePathCallback;

                // ACTION_OPEN_DOCUMENT opens Android's real Files app -
                // the same folder browser used across almost every
                // Android device/brand, unlike ACTION_GET_CONTENT which
                // can resolve to a Share sheet on some OEM Android skins.
                //
                // BUG FIX: previously restricted selectable files using
                // fileChooserParams.getAcceptTypes() via EXTRA_MIME_TYPES.
                // Some HTML file inputs specify "accept" using file
                // extensions (e.g. ".jpg,.png") rather than proper MIME
                // types (e.g. "image/*") - Android's system picker expects
                // real MIME types there, and silently hides/blocks files
                // that don't match cleanly. Since the underlying HTML form
                // already accepts a wide range of document/image types,
                // it's safer and more reliable to let the user pick ANY
                // file here rather than risk incorrectly hiding valid ones.
                Intent intent = new Intent(Intent.ACTION_OPEN_DOCUMENT);
                intent.addCategory(Intent.CATEGORY_OPENABLE);
                intent.setType("*/*");

                if (fileChooserParams.getMode() == FileChooserParams.MODE_OPEN_MULTIPLE) {
                    intent.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
                }

                try {
                    fileChooserLauncher.launch(intent);
                } catch (ActivityNotFoundException e) {
                    try {
                        Intent fallback = new Intent(Intent.ACTION_GET_CONTENT);
                        fallback.addCategory(Intent.CATEGORY_OPENABLE);
                        fallback.setType("*/*");
                        fileChooserLauncher.launch(fallback);
                    } catch (Exception e2) {
                        MainActivity.this.filePathCallback = null;
                        Toast.makeText(
                            MainActivity.this,
                            "No file picker app is available on this device.",
                            Toast.LENGTH_LONG
                        ).show();
                        return false;
                    }
                } catch (Exception e) {
                    MainActivity.this.filePathCallback = null;
                    Toast.makeText(
                        MainActivity.this,
                        "Unable to open file picker.",
                        Toast.LENGTH_LONG
                    ).show();
                    return false;
                }

                return true;
            }
        });
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);

        if (requestCode != CAMERA_PERMISSION_REQUEST_CODE) {
            return;
        }

        boolean granted = grantResults.length > 0
            && grantResults[0] == PackageManager.PERMISSION_GRANTED;

        // Case 1: the website's own JS (getUserMedia) triggered this.
        if (pendingWebPermissionRequest != null) {
            if (granted) {
                pendingWebPermissionRequest.grant(
                    new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE}
                );
            } else {
                pendingWebPermissionRequest.deny();
            }
            pendingWebPermissionRequest = null;
        }

        // Case 2: our own native camera_permission.html screen triggered this.
        if (pendingFaceUrl != null) {
            if (!granted) {
                Toast.makeText(
                    this,
                    "Camera permission is needed for face registration/verification.",
                    Toast.LENGTH_LONG
                ).show();
            }
            continueToPendingFaceUrl();
        }
    }

    // Called when the user taps "Allow Camera Access" on our local
    // camera_permission.html screen. Handles the case Android otherwise
    // handles silently: if the user denied camera access before, Android
    // stops showing its own popup on later requests - so tapping Allow
    // would appear to do nothing at all. We track whether we've asked
    // before ourselves, and once that's true and Android still won't
    // show a prompt, send the user to the app's system Settings page
    // instead, where they can grant it manually.
    private void requestCameraPermissionNatively() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA)
                == PackageManager.PERMISSION_GRANTED) {
            continueToPendingFaceUrl();
            return;
        }

        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        boolean askedBefore = prefs.getBoolean(PREF_CAMERA_ASKED_BEFORE, false);
        boolean canShowSystemPrompt = ActivityCompat.shouldShowRequestPermissionRationale(
            this, Manifest.permission.CAMERA
        );

        if (askedBefore && !canShowSystemPrompt) {
            // Android has permanently stopped showing its own popup for
            // this permission - the only way forward is the app's
            // Settings page.
            Toast.makeText(
                this,
                "Camera was previously denied. Enable it in Settings > Permissions.",
                Toast.LENGTH_LONG
            ).show();

            Intent settingsIntent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS);
            settingsIntent.setData(Uri.fromParts("package", getPackageName(), null));
            try {
                startActivity(settingsIntent);
            } catch (ActivityNotFoundException e) {
                // No settings screen available - fall back to continuing
                // without camera access rather than getting stuck.
            }
            continueToPendingFaceUrl();
            return;
        }

        prefs.edit().putBoolean(PREF_CAMERA_ASKED_BEFORE, true).apply();

        ActivityCompat.requestPermissions(
            this,
            new String[]{Manifest.permission.CAMERA},
            CAMERA_PERMISSION_REQUEST_CODE
        );
    }

    // Continues to whichever page the user was originally trying to
    // reach (face registration/verification) before the camera
    // permission screen interrupted them.
    private void continueToPendingFaceUrl() {
        String target = pendingFaceUrl != null ? pendingFaceUrl : REMOTE_URL;
        pendingFaceUrl = null;
        if (webView != null) {
            webView.loadUrl(target);
        }
    }

    private boolean isNetworkAvailable() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) {
            return false;
        }
        Network network = cm.getActiveNetwork();
        if (network == null) {
            return false;
        }
        NetworkCapabilities capabilities = cm.getNetworkCapabilities(network);
        return capabilities != null && capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET);
    }

    // Watches for connectivity changes in the background - as soon as the
    // connection comes back while the offline page is showing, automatically
    // load the live site again instead of making the user tap Retry.
    private void registerNetworkCallback() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) {
            return;
        }

        networkCallback = new ConnectivityManager.NetworkCallback() {
            @Override
            public void onAvailable(Network network) {
                runOnUiThread(() -> {
                    if (webView != null && OFFLINE_URL.equals(webView.getUrl())) {
                        webView.loadUrl(REMOTE_URL);
                    }
                });
            }
        };

        NetworkRequest request = new NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .build();

        cm.registerNetworkCallback(request, networkCallback);
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        if (networkCallback != null) {
            ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm != null) {
                try {
                    cm.unregisterNetworkCallback(networkCallback);
                } catch (Exception ignored) {
                }
            }
        }
    }
}
