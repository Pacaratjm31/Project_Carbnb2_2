package com.carbnb.app;

import android.Manifest;
import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
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

    // Brand colors, matching the website's dark theme (admin_style.css :root)
    private static final int COLOR_BG = 0xFF0F1115;
    private static final int COLOR_PANEL = 0xFF171C24;
    private static final int COLOR_TEXT = 0xFFF5F7FB;
    private static final int COLOR_MUTED = 0xFFA8B0BF;
    private static final int COLOR_ACCENT = 0xFFF5B942;   // gold
    private static final int COLOR_ACCENT_2 = 0xFF4CC9F0; // blue
    private static final int COLOR_BORDER = 0x1AFFFFFF;

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

        // BUG FIX: Capacitor starts loading the live site as part of its
        // own bridge setup inside super.onCreate() above - BEFORE our
        // custom WebViewClient (below) is attached. If there's no
        // connection at cold start, that very first failure can be
        // handled by Capacitor's default client instead of ours, so
        // Android's plain built-in error page shows instead of our
        // screen. Checking connectivity directly here, rather than only
        // reacting to the WebView's error callback, closes that gap -
        // this covers the WebView immediately regardless of any timing
        // race with the page load.
        if (!isNetworkAvailable()) {
            showNoInternetOverlay();
        }

        // Extra safety net: re-check shortly after launch too. Covers the
        // rare case where the very first page-load failure is handled by
        // Capacitor's own default client before ours is attached below,
        // so neither the check above nor our onReceivedError override
        // catches it.
        webView.postDelayed(() -> {
            if (!isNetworkAvailable()) {
                showNoInternetOverlay();
            }
        }, 2500);

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

                // BUG FIX (v4): fileChooserParams.createIntent() (ACTION_GET_CONTENT)
                // wrapped in Intent.createChooser() was resolving to Android's
                // SHARE sheet ("Share via Nearby Share...") on some phones/OEM
                // Android skins instead of a real file picker - completely
                // unusable, since sharing isn't picking.
                //
                // ACTION_OPEN_DOCUMENT is a different, more standardized system
                // action handled directly by Android's built-in Files app
                // (DocumentsUI) on virtually every Android version/brand - it
                // shows an actual folder browser (Downloads, Photos, Drive,
                // "This device", etc.), matching the normal desktop-browser
                // file-picking experience.
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
                    // Fall back to the simpler GET_CONTENT action if
                    // OPEN_DOCUMENT somehow isn't available on this device.
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

    // ------------------------------------------------------------------
    // No internet connection overlay - styled to match the app's branding
    // ------------------------------------------------------------------

    private void setupNoInternetOverlay() {
        FrameLayout overlay = new FrameLayout(this);
        overlay.setBackgroundColor(COLOR_BG);
        overlay.setVisibility(View.GONE);
        overlay.setClickable(true); // blocks touches from passing through to the WebView underneath

        // --- Rounded card ---
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setGravity(Gravity.CENTER);
        card.setPadding(64, 72, 64, 72);

        GradientDrawable cardBg = new GradientDrawable();
        cardBg.setColor(COLOR_PANEL);
        cardBg.setCornerRadius(36f);
        cardBg.setStroke(2, COLOR_BORDER);
        card.setBackground(cardBg);

        FrameLayout.LayoutParams cardParams = new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT,
            Gravity.CENTER
        );
        cardParams.leftMargin = 48;
        cardParams.rightMargin = 48;
        card.setLayoutParams(cardParams);
        card.setElevation(24f);

        // --- Icon circle ---
        FrameLayout iconCircle = new FrameLayout(this);
        GradientDrawable circleBg = new GradientDrawable();
        circleBg.setShape(GradientDrawable.OVAL);
        circleBg.setColor(0x224CC9F0); // translucent accent-2
        LinearLayout.LayoutParams circleParams = new LinearLayout.LayoutParams(160, 160);
        circleParams.bottomMargin = 32;
        circleParams.gravity = Gravity.CENTER_HORIZONTAL;
        iconCircle.setLayoutParams(circleParams);
        iconCircle.setBackground(circleBg);

        TextView icon = new TextView(this);
        icon.setText("\uD83D\uDCF6"); // 📶
        icon.setTextSize(44);
        icon.setGravity(Gravity.CENTER);
        FrameLayout.LayoutParams iconInnerParams = new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT,
            Gravity.CENTER
        );
        icon.setLayoutParams(iconInnerParams);
        iconCircle.addView(icon);

        // --- Title ---
        TextView title = new TextView(this);
        title.setText("No Internet Connection");
        title.setTextColor(COLOR_TEXT);
        title.setTextSize(20);
        title.setGravity(Gravity.CENTER);
        title.setTypeface(title.getTypeface(), android.graphics.Typeface.BOLD);
        LinearLayout.LayoutParams titleParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        );
        titleParams.bottomMargin = 14;
        title.setLayoutParams(titleParams);

        // --- Subtitle ---
        TextView subtitle = new TextView(this);
        subtitle.setText("Please check your Wi-Fi or mobile data, then try again.");
        subtitle.setTextColor(COLOR_MUTED);
        subtitle.setTextSize(14);
        subtitle.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams subtitleParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        );
        subtitleParams.bottomMargin = 36;
        subtitle.setLayoutParams(subtitleParams);
        subtitle.setMaxWidth(720);

        // --- Retry button (gold accent, rounded pill) ---
        Button retryButton = new Button(this);
        retryButton.setText("Retry");
        retryButton.setTextColor(0xFF111111);
        retryButton.setTextSize(15);
        retryButton.setAllCaps(false);
        retryButton.setTypeface(retryButton.getTypeface(), android.graphics.Typeface.BOLD);
        retryButton.setPadding(72, 28, 72, 28);
        retryButton.setStateListAnimator(null);

        GradientDrawable buttonBg = new GradientDrawable(
            GradientDrawable.Orientation.LEFT_RIGHT,
            new int[]{COLOR_ACCENT, 0xFFFFCF66}
        );
        buttonBg.setCornerRadius(100f);
        retryButton.setBackground(buttonBg);

        LinearLayout.LayoutParams retryParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        );
        retryButton.setLayoutParams(retryParams);

        retryButton.setOnClickListener(v -> {
            if (isNetworkAvailable()) {
                hideNoInternetOverlay();
                webView.reload();
            } else {
                Toast.makeText(this, "Still no connection.", Toast.LENGTH_SHORT).show();
            }
        });

        card.addView(iconCircle);
        card.addView(title);
        card.addView(subtitle);
        card.addView(retryButton);
        overlay.addView(card);

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
