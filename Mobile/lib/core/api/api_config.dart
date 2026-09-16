/// Production API base URL used by all builds (debug and release).
const String productionApiBaseUrl =
    'https://fieldtrack.paramsocialfoundation.org/api';

class ApiConfig {
  /// Always the live production API — no local/LAN/debug host switching.
  static String get baseUrl => productionApiBaseUrl;
}
