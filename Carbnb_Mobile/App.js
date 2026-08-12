import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { StatusBar } from 'expo-status-bar';
import NetInfo from '@react-native-community/netinfo';
import { WebView } from 'react-native-webview';

// =====================================================
// CARBNB SERVER
// Local network testing server
// =====================================================

const CARBNB_URL =
  'https://carbnb.free.je/';

export default function App() {
  const webViewRef = useRef(null);

  const [isConnected, setIsConnected] = useState(true);
  const [loading, setLoading] = useState(true);
  const [hasError, setHasError] = useState(false);

  useEffect(() => {
    const checkConnection = (state) => {
      const connected =
        state.isConnected === true &&
        state.isInternetReachable !== false;

      setIsConnected(connected);

      if (!connected) {
        setLoading(false);
      }
    };

    NetInfo.fetch().then(checkConnection);

    const unsubscribe = NetInfo.addEventListener(checkConnection);

    return () => unsubscribe();
  }, []);

  const retryConnection = async () => {
    setHasError(false);
    setLoading(true);

    const state = await NetInfo.fetch();

    const connected =
      state.isConnected === true &&
      state.isInternetReachable !== false;

    setIsConnected(connected);

    if (connected && webViewRef.current) {
      webViewRef.current.reload();
    }
  };

  // =====================================================
  // NO INTERNET
  // =====================================================
  if (!isConnected) {
    return (
      <SafeAreaView style={styles.container}>
        <StatusBar style="light" />

        <View style={styles.messageContainer}>

          <View style={styles.logoContainer}>
            <Text style={styles.logoCar}>Car</Text>
            <Text style={styles.logoBnb}>bnb</Text>
          </View>

          <View style={styles.iconCircle}>
            <Text style={styles.icon}>📡</Text>
          </View>

          <Text style={styles.title}>
            No Internet Connection
          </Text>

          <Text style={styles.message}>
            Carbnb needs an internet connection to continue.
            Please check your Wi-Fi or mobile data and try again.
          </Text>

          <TouchableOpacity
            style={styles.button}
            onPress={retryConnection}
            activeOpacity={0.8}
          >
            <Text style={styles.buttonText}>
              Try Again
            </Text>
          </TouchableOpacity>

          <Text style={styles.footerText}>
            Carbnb • Drive Luxury. Rent Easily.
          </Text>

        </View>
      </SafeAreaView>
    );
  }

  // =====================================================
  // SERVER CONNECTION ERROR
  // =====================================================
  if (hasError) {
    return (
      <SafeAreaView style={styles.container}>
        <StatusBar style="light" />

        <View style={styles.messageContainer}>

          <View style={styles.logoContainer}>
            <Text style={styles.logoCar}>Car</Text>
            <Text style={styles.logoBnb}>bnb</Text>
          </View>

          <View style={styles.errorCircle}>
            <Text style={styles.icon}>⚠️</Text>
          </View>

          <Text style={styles.title}>
            Carbnb Can't Connect
          </Text>

          <Text style={styles.message}>
            We couldn't connect to the Carbnb server.
            Please try again in a moment.
          </Text>

          <TouchableOpacity
            style={styles.button}
            onPress={retryConnection}
            activeOpacity={0.8}
          >
            <Text style={styles.buttonText}>
              Try Again
            </Text>
          </TouchableOpacity>

          <Text style={styles.footerText}>
            Carbnb • Drive Luxury. Rent Easily.
          </Text>

        </View>
      </SafeAreaView>
    );
  }

  // =====================================================
  // CARBNB WEBVIEW
  // =====================================================
  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="light" />

      {loading && (
        <View style={styles.loadingContainer}>
          <View style={styles.logoContainer}>
            <Text style={styles.logoCar}>Car</Text>
            <Text style={styles.logoBnb}>bnb</Text>
          </View>

          <ActivityIndicator
            size="large"
            color="#00bfff"
          />

          <Text style={styles.loadingText}>
            Loading Carbnb...
          </Text>
        </View>
      )}

      <WebView
        ref={webViewRef}
        source={{ uri: CARBNB_URL }}
        style={styles.webview}
        javaScriptEnabled={true}
        domStorageEnabled={true}
        onLoadStart={() => {
          setLoading(true);
          setHasError(false);
        }}
        onLoadEnd={() => {
          setLoading(false);
        }}
        onError={() => {
          setLoading(false);
          setHasError(true);
        }}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  // =====================================================
  // DARK METALLIC CARBNB THEME
  // =====================================================

  container: {
    flex: 1,
    backgroundColor: '#1e1e1e',
  },

  webview: {
    flex: 1,
  },

  messageContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 28,
    backgroundColor: '#1e1e1e',
  },

  // =====================================================
  // CARBNB LOGO
  // =====================================================

  logoContainer: {
    flexDirection: 'row',
    marginBottom: 30,
  },

  logoCar: {
    color: '#00bfff',
    fontSize: 38,
    fontWeight: '800',
  },

  logoBnb: {
    color: '#ff8c00',
    fontSize: 38,
    fontWeight: '800',
  },

  // =====================================================
  // CONNECTION ICON
  // =====================================================

  iconCircle: {
    width: 92,
    height: 92,
    borderRadius: 46,
    backgroundColor: '#2a2a2a',
    borderWidth: 2,
    borderColor: '#00bfff',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,

    shadowColor: '#00bfff',
    shadowOffset: {
      width: 0,
      height: 0,
    },
    shadowOpacity: 0.35,
    shadowRadius: 12,
    elevation: 8,
  },

  errorCircle: {
    width: 92,
    height: 92,
    borderRadius: 46,
    backgroundColor: '#2a2a2a',
    borderWidth: 2,
    borderColor: '#ff8c00',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,

    shadowColor: '#ff8c00',
    shadowOffset: {
      width: 0,
      height: 0,
    },
    shadowOpacity: 0.35,
    shadowRadius: 12,
    elevation: 8,
  },

  icon: {
    fontSize: 42,
  },

  // =====================================================
  // MESSAGE
  // =====================================================

  title: {
    color: '#ffffff',
    fontSize: 25,
    fontWeight: '700',
    textAlign: 'center',
    marginBottom: 14,
  },

  message: {
    color: '#cfcfcf',
    fontSize: 16,
    lineHeight: 25,
    textAlign: 'center',
    maxWidth: 360,
    marginBottom: 28,
  },

  // =====================================================
  // ORANGE CARBNB BUTTON
  // =====================================================

  button: {
    backgroundColor: '#ff8c00',
    paddingVertical: 14,
    paddingHorizontal: 38,
    borderRadius: 30,
    borderWidth: 1,
    borderColor: '#ffad42',

    shadowColor: '#ff8c00',
    shadowOffset: {
      width: 0,
      height: 5,
    },
    shadowOpacity: 0.3,
    shadowRadius: 10,
    elevation: 6,
  },

  buttonText: {
    color: '#1a1a1a',
    fontSize: 16,
    fontWeight: '800',
  },

  // =====================================================
  // FOOTER
  // =====================================================

  footerText: {
    color: '#777777',
    fontSize: 12,
    marginTop: 35,
    textAlign: 'center',
  },

  // =====================================================
  // LOADING
  // =====================================================

  loadingContainer: {
    position: 'absolute',
    zIndex: 10,
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#1e1e1e',
  },

  loadingText: {
    color: '#cfcfcf',
    fontSize: 16,
    marginTop: 15,
  },
});