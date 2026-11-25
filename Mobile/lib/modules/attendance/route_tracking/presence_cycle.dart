import 'dart:async';
import 'dart:io';

import 'package:geolocator/geolocator.dart';

import 'presence_event_store.dart';
import 'route_capture_rules.dart';

typedef PresenceUpload = Future<bool> Function(Map<String, dynamic> payload);
typedef PresenceFix = ({double? latitude, double? longitude, bool valid});

/// Samples location-service and connectivity state on the existing tracking cycle.
class PresenceCycle {
  PresenceCycle({
    required this.store,
    required this.upload,
    this.locationServicesEnabled,
    this.internetOnline,
    this.readFix,
    this.clock,
    this.newUuid,
  });

  final PresenceEventStore store;
  final PresenceUpload upload;
  final Future<bool> Function()? locationServicesEnabled;
  final Future<bool> Function()? internetOnline;
  final Future<PresenceFix> Function()? readFix;
  final DateTime Function()? clock;
  final String Function()? newUuid;

  Future<void> tick({
    required int attendanceId,
    double? lastLatitude,
    double? lastLongitude,
    bool heartbeat = true,
  }) async {
    bool? services;
    bool? online;
    try {
      services = await (locationServicesEnabled ?? _services)();
    } catch (_) {
      services = null;
    }
    try {
      online = await (internetOnline ?? _online)();
    } catch (_) {
      online = null;
    }

    var latitude = lastLatitude;
    var longitude = lastLongitude;
    var fixValid = false;
    if (services == true) {
      try {
        final fix = await (readFix ?? _lastKnown)();
        latitude = fix.latitude ?? latitude;
        longitude = fix.longitude ?? longitude;
        fixValid = fix.valid;
      } catch (_) {
        fixValid = false;
      }
    }

    await PresenceEventQueue(store, newUuid: newUuid).apply(
      attendanceId: attendanceId,
      at: (clock ?? DateTime.now)(),
      locationServicesEnabled: services,
      internetOnline: online,
      latitude: latitude,
      longitude: longitude,
    );

    await flush(
      attendanceId: attendanceId,
      heartbeat: heartbeat,
      gpsServicesEnabled: services,
      gpsFixValid: services == true && fixValid,
      latitude: latitude,
      longitude: longitude,
    );
  }

  /// Sends queued events. Does not record a new sample.
  Future<void> flush({
    required int attendanceId,
    required bool heartbeat,
    bool? gpsServicesEnabled,
    bool? gpsFixValid,
    double? latitude,
    double? longitude,
  }) async {
    final pending = store.pendingFor(attendanceId);
    if (pending.isEmpty && heartbeat == false) return;
    final payload = <String, dynamic>{
      'attendance_id': attendanceId,
      'heartbeat': heartbeat,
      'gps_services_enabled': ?gpsServicesEnabled,
      'gps_fix_valid': ?gpsFixValid,
      'latitude': ?latitude,
      'longitude': ?longitude,
      'events': pending.map((event) => event.toApi()).toList(),
    };
    try {
      final ok = await upload(payload);
      if (ok) {
        await store.markSynced(pending.map((event) => event.localUuid));
      }
    } catch (_) {
      // Keep the queue for the next successful request.
    }
  }

  static Future<bool> _services() => Geolocator.isLocationServiceEnabled();

  static Future<bool> _online() async {
    try {
      final result = await InternetAddress.lookup(
        'dns.google',
      ).timeout(const Duration(seconds: 3));
      return result.isNotEmpty && result.first.rawAddress.isNotEmpty;
    } on SocketException {
      return false;
    } on TimeoutException {
      return false;
    }
  }

  static Future<PresenceFix> _lastKnown() async {
    final position = await Geolocator.getLastKnownPosition();
    if (position == null) {
      return (latitude: null, longitude: null, valid: false);
    }
    final fresh = DateTime.now().difference(position.timestamp).inMinutes < 5;
    final valid =
        fresh &&
        RouteCaptureRules.isValidCoordinate(
          position.latitude,
          position.longitude,
        ) &&
        RouteCaptureRules.hasAcceptableAccuracy(position.accuracy);
    return (
      latitude: position.latitude,
      longitude: position.longitude,
      valid: valid,
    );
  }
}
