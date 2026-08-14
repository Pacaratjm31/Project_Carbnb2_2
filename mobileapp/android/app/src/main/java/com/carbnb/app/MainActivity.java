package com.carbnb.app;

import android.os.Bundle;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.widget.Toast;

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

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        WebView webView = this.bridge.getWebView();

        webView.setWebViewClient(new BridgeWebViewClient(this.bridge) {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();
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
                            // Block navigation entirely - do not load the admin page.
                            return true;
                        }
                    }
                }

                // Not an admin URL - let Capacitor's default handling proceed as normal.
                return super.shouldOverrideUrlLoading(view, request);
            }
        });
    }
}
