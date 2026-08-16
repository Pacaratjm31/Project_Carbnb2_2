package com.carbnb.app;

import android.Manifest;
import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.net.Uri;
import android.os.Bundle;
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
                Intent intent = new Intent(Intent.ACTION_OPEN_DOCUMENT);
                intent.addCategory(Intent.CATEGORY_OPENABLE);
                intent.setType("*/*");

                String[] acceptTypes = fileChooserParams.getAcceptTypes();
                if (acceptTypes != null
                        && acceptTypes.length > 0
                        && !(acceptTypes.length == 1 && acceptTypes[0].isEmpty())) {
                    intent.putExtra(Intent.EXTRA_MIME_TYPES, acceptTypes);
                }

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

        if (requestCode == CAMERA_PERMISSION_REQUEST_CODE && pendingWebPermissionRequest != null) {
            boolean granted = grantResults.length > 0
                && grantResults[0] == PackageManager.PERMISSION_GRANTED;

            if (granted) {
                pendingWebPermissionRequest.grant(
                    new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE}
                );
            } else {
                pendingWebPermissionRequest.deny();
                Toast.makeText(
                    this,
                    "Camera permission is needed for face registration/verification.",
                    Toast.LENGTH_LONG
                ).show();
            }

            pendingWebPermissionRequest = null;
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
