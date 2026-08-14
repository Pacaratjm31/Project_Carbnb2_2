package com.carbnb.app;

import android.Manifest;
import android.content.pm.PackageManager;
import android.os.Bundle;
import android.webkit.PermissionRequest;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.widget.Toast;

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

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        WebView webView = this.bridge.getWebView();

        // --- Admin-page blocking (unchanged from before) ---
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
        });

        // --- Camera permission handling for face registration/verification ---
        // The website calls the browser's getUserMedia() camera API (used by
        // face-api.js). Android WebView blocks this by default unless the
        // app explicitly grants it here, AND the user has approved the
        // normal Android camera permission dialog.
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
}
