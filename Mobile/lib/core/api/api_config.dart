/// Live FieldTrack API. Rebuild the APK after changing this value.
/// Local emulator override:
/// flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
const String productionApiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://fieldtrack.paramgold.in/api',
);

class ApiConfig {
  static String get baseUrl => productionApiBaseUrl;
}
