package com.carbnb.app;

import android.Manifest;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.net.Uri;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.PermissionRequest;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;
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

    // Holds the website's pending camera request while we wait for the
    // user to respond to Android's own "Allow Carbnb to use the camera?"
    // system dialog. Once that resolves, we grant or deny this.
    private PermissionRequest pendingWebPermissionRequest;

    // --- File chooser support (Valid ID / Proof of Billing / etc. uploads) ---
    // A plain WebView has NO built-in "Choose File" picker - unlike Chrome,
    // it does nothing at all unless the app explicitly opens Android's own
    // file picker and hands the result back to the website. This callback
    // is how the website receives whatever file the user picked.
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
                    // Multiple files selected
                    int count = data.getClipData().getItemCount();
                    results = new Uri[count];
                    for (int i = 0; i < count; i++) {
                        results[i] = data.getClipData().getItemAt(i).getUri();
                    }
                } else if (data.getData() != null) {
                    // Single file selected
                    results = new Uri[]{ data.getData() };
                }
            }

            filePathCallback.onReceiveValue(results);
            filePathCallback = null;
        });

    // --- No internet connection handling ---
    private View noInternetOverlay;
    private WebView webView;
    private ConnectivityManager.NetworkCallback networkCallback;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        webView = this.bridge.getWebView();

        setupNoInternetOverlay();
        registerNetworkCallback();

        // --- Admin-page blocking + no-internet detection ---
        webView.setWebViewClient(new BridgeWebViewClient(this.bridge) {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
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
                // a background image/script request failing.
                if (request.isForMainFrame()) {
                    showNoInternetOverlay();
                }
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                // A page finished loading successfully - hide any leftover
                // no-internet message.
                hideNoInternetOverlay();
            }
        });

        // --- Camera permission + file chooser for the website ---
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
                        // Not asking for camera (e.g. microphone) - deny by
                        // default since the app doesn't use audio capture.
                        request.deny();
                        return;
                    }

                    if (ContextCompat.checkSelfPermission(MainActivity.this, Manifest.permission.CAMERA)
                            == PackageManager.PERMISSION_GRANTED) {
                        // Android-level permission already granted - let the
                        // website's camera request through immediately.
                        request.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
                    } else {
                        // Not granted yet - ask the user via the normal
                        // system permission dialog. The website's request
                        // is held until onRequestPermissionsResult below.
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
                // Cancel any previous pending chooser before starting a new one.
                if (MainActivity.this.filePathCallback != null) {
                    MainActivity.this.filePathCallback.onReceiveValue(null);
                }
                MainActivity.this.filePathCallback = filePathCallback;

                try {
                    Intent intent = fileChooserParams.createIntent();
                    fileChooserLauncher.launch(intent);
                } catch (Exception e) {
                    MainActivity.this.filePathCallback = null;
                    Toast.makeText(
                        MainActivity.this,
                        "Unable to open file picker.",
                        Toast.LENGTH_SHORT
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

    // ------------------------------------------------------------------
    // No internet connection overlay
    // ------------------------------------------------------------------

    private void setupNoInternetOverlay() {
        FrameLayout overlay = new FrameLayout(this);
        overlay.setBackgroundColor(0xFF1e1e1e); // matches the app's dark theme
        overlay.setVisibility(View.GONE);
        overlay.setClickable(true); // blocks touches from passing through to the WebView underneath

        LinearLayout content = new LinearLayout(this);
        content.setOrientation(LinearLayout.VERTICAL);
        content.setGravity(Gravity.CENTER);
        content.setPadding(80, 80, 80, 80);

        FrameLayout.LayoutParams contentParams = new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT,
            Gravity.CENTER
        );
        content.setLayoutParams(contentParams);

        TextView icon = new TextView(this);
        icon.setText("\uD83D\uDCF6"); // 📶 signal icon
        icon.setTextSize(48);
        icon.setGravity(Gravity.CENTER);
        icon.setPadding(0, 0, 0, 24);

        TextView title = new TextView(this);
        title.setText("No Internet Connection");
        title.setTextColor(0xFFF5F7FB);
        title.setTextSize(20);
        title.setGravity(Gravity.CENTER);
        title.setPadding(0, 0, 0, 12);

        TextView subtitle = new TextView(this);
        subtitle.setText("Please check your Wi-Fi or mobile data and try again.");
        subtitle.setTextColor(0xFFA8B0BF);
        subtitle.setTextSize(14);
        subtitle.setGravity(Gravity.CENTER);
        subtitle.setPadding(20, 0, 20, 32);

        Button retryButton = new Button(this);
        retryButton.setText("Retry");
        retryButton.setTextColor(0xFF111111);
        retryButton.setBackgroundColor(0xFFF5B942); // matches the app's accent color
        retryButton.setOnClickListener(v -> {
            if (isNetworkAvailable()) {
                hideNoInternetOverlay();
                webView.reload();
            } else {
                Toast.makeText(this, "Still no connection.", Toast.LENGTH_SHORT).show();
            }
        });

        content.addView(icon);
        content.addView(title);
        content.addView(subtitle);
        content.addView(retryButton);
        overlay.addView(content);

        this.noInternetOverlay = overlay;

        addContentView(
            overlay,
            new ViewGroup.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            )
        );
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

    private void showNoInternetOverlay() {
        if (noInternetOverlay != null) {
            runOnUiThread(() -> noInternetOverlay.setVisibility(View.VISIBLE));
        }
    }

    private void hideNoInternetOverlay() {
        if (noInternetOverlay != null) {
            runOnUiThread(() -> noInternetOverlay.setVisibility(View.GONE));
        }
    }

    // Watches for connectivity changes in the background - as soon as the
    // connection comes back, automatically hide the message and reload
    // the page, instead of making the user manually tap Retry.
    private void registerNetworkCallback() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) {
            return;
        }

        networkCallback = new ConnectivityManager.NetworkCallback() {
            @Override
            public void onAvailable(Network network) {
                runOnUiThread(() -> {
                    if (noInternetOverlay != null && noInternetOverlay.getVisibility() == View.VISIBLE) {
                        hideNoInternetOverlay();
                        webView.reload();
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
                    // Already unregistered or never registered - safe to ignore.
                }
            }
        }
    }
}
