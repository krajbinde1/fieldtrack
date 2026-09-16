import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';
import 'app_version_info.dart';

/// Persists a confirmed force-update so a later API outage cannot bypass it.
/// Optional (skip/later) builds are stored separately and never lock the app.
class AppUpdateStore {
  static const _confirmedKey = 'paramfieldtrack_confirmed_app_update';
  static const _skippedBuildKey = 'paramfieldtrack_skipped_app_update_build';

  Future<AppVersionInfo?> readConfirmed() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_confirmedKey);
    if (raw == null || raw.isEmpty) return null;
    try {
      final map = jsonDecode(raw);
      if (map is! Map) return null;
      return AppVersionInfo.fromJson(Map<String, dynamic>.from(map));
    } catch (_) {
      return null;
    }
  }

  Future<void> saveConfirmed(AppVersionInfo info) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_confirmedKey, jsonEncode(info.toJson()));
  }

  Future<int> readSkippedBuild() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(_skippedBuildKey) ?? 0;
  }

  Future<void> saveSkippedBuild(int build) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_skippedBuildKey, build);
  }

  Future<void> clearConfirmed() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_confirmedKey);
  }

  Future<void> clearSkipped() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_skippedBuildKey);
  }

  Future<void> clear() async {
    await clearConfirmed();
    await clearSkipped();
  }
}
