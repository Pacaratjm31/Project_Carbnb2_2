import { StatusBar } from 'expo-status-bar';
import { StyleSheet, SafeAreaView, Platform } from 'react-native';
import { WebView } from 'react-native-webview';

export default function WebViewScreen({
  url,
  style,
  statusBarStyle = 'auto',
  javaScriptEnabled = true,
  domStorageEnabled = true,
  allowsInlineMediaPlayback = true,
  allowsBackForwardNavigationGestures = true,
  originWhitelist = ['*'],
  onLoadStart,
  onLoadEnd,
  onError,
  ...webViewProps
}) {
  return (
    <SafeAreaView style={[styles.container, style]}>
      <StatusBar style={statusBarStyle} />
      <WebView
        source={{ uri: url }}
        style={styles.webview}
        javaScriptEnabled={javaScriptEnabled}
        domStorageEnabled={domStorageEnabled}
        startInLoadingState={true}
        allowsInlineMediaPlayback={allowsInlineMediaPlayback}
        mediaPlaybackRequiresUserAction={false}
        allowsBackForwardNavigationGestures={allowsBackForwardNavigationGestures}
        originWhitelist={originWhitelist}
        onLoadStart={onLoadStart}
        onLoadEnd={onLoadEnd}
        onError={onError}
        {...webViewProps}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    paddingTop: Platform.OS === 'android' ? 24 : 0,
  },
  webview: {
    flex: 1,
  },
});
