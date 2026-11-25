import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import 'presence_event.dart';

abstract class PresenceEventStore {
  List<PresenceEvent> pendingFor(int attendanceId);

  List<PresenceEvent> pending();

  Future<void> add(PresenceEvent event);

  Future<void> markSynced(Iterable<String> localUuids);

  bool? get lastLocationServicesEnabled;

  bool? get lastInternetOnline;

  Future<void> setFlags({bool? locationServicesEnabled, bool? internetOnline});
}

class MemoryPresenceEventStore implements PresenceEventStore {
  final List<PresenceEvent> _events = [];
  bool? _locationServicesEnabled;
  bool? _internetOnline;

  @override
  List<PresenceEvent> pending() => List.unmodifiable(_events);

  @override
  List<PresenceEvent> pendingFor(int attendanceId) =>
      _events.where((event) => event.attendanceId == attendanceId).toList();

  @override
  Future<void> add(PresenceEvent event) async {
    if (_events.any((existing) => existing.localUuid == event.localUuid)) {
      return;
    }
    _events.add(event);
  }

  @override
  Future<void> markSynced(Iterable<String> localUuids) async {
    final synced = localUuids.toSet();
    _events.removeWhere((event) => synced.contains(event.localUuid));
  }

  @override
  bool? get lastLocationServicesEnabled => _locationServicesEnabled;

  @override
  bool? get lastInternetOnline => _internetOnline;

  @override
  Future<void> setFlags({
    bool? locationServicesEnabled,
    bool? internetOnline,
  }) async {
    if (locationServicesEnabled != null) {
      _locationServicesEnabled = locationServicesEnabled;
    }
    if (internetOnline != null) {
      _internetOnline = internetOnline;
    }
  }
}

class SharedPrefsPresenceEventStore implements PresenceEventStore {
  SharedPrefsPresenceEventStore(this._prefs);

  static const queueKey = 'presence_event_queue';
  static const flagsKey = 'presence_event_flags';

  final SharedPreferences _prefs;

  @override
  List<PresenceEvent> pending() => _read();

  @override
  List<PresenceEvent> pendingFor(int attendanceId) => _read()
      .where((event) => event.attendanceId == attendanceId)
      .toList();

  @override
  Future<void> add(PresenceEvent event) async {
    final events = _read();
    if (events.any((existing) => existing.localUuid == event.localUuid)) {
      return;
    }
    events.add(event);
    await _write(events);
  }

  @override
  Future<void> markSynced(Iterable<String> localUuids) async {
    final synced = localUuids.toSet();
    if (synced.isEmpty) return;
    await _write(
      _read().where((event) => !synced.contains(event.localUuid)).toList(),
    );
  }

  @override
  bool? get lastLocationServicesEnabled => _flag('location_services_enabled');

  @override
  bool? get lastInternetOnline => _flag('internet_online');

  @override
  Future<void> setFlags({
    bool? locationServicesEnabled,
    bool? internetOnline,
  }) async {
    final flags = _flags();
    if (locationServicesEnabled != null) {
      flags['location_services_enabled'] = locationServicesEnabled;
    }
    if (internetOnline != null) {
      flags['internet_online'] = internetOnline;
    }
    await _prefs.setString(flagsKey, jsonEncode(flags));
  }

  List<PresenceEvent> _read() {
    final raw = _prefs.getString(queueKey);
    if (raw == null || raw.isEmpty) return [];
    final decoded = jsonDecode(raw);
    if (decoded is! List) return [];
    return decoded
        .whereType<Map>()
        .map((item) => PresenceEvent.fromJson(Map<String, dynamic>.from(item)))
        .where((event) => event.localUuid.isNotEmpty)
        .toList();
  }

  Future<void> _write(List<PresenceEvent> events) {
    return _prefs.setString(
      queueKey,
      jsonEncode(events.map((event) => event.toJson()).toList()),
    );
  }

  Map<String, dynamic> _flags() {
    final raw = _prefs.getString(flagsKey);
    if (raw == null || raw.isEmpty) return {};
    final decoded = jsonDecode(raw);
    if (decoded is! Map) return {};
    return Map<String, dynamic>.from(decoded);
  }

  bool? _flag(String key) {
    final value = _flags()[key];
    if (value is bool) return value;
    return null;
  }
}

class PresenceEventQueue {
  PresenceEventQueue(this.store, {this.newUuid});

  final PresenceEventStore store;
  final String Function()? newUuid;
  int _sequence = 0;

  /// Records GPS and connectivity transitions.
  ///
  /// The first sample establishes a baseline. A later sample queues an event
  /// only when the state changes, so a repeated GPS-off reading is not lost
  /// and is not duplicated.
  Future<List<PresenceEvent>> apply({
    required int attendanceId,
    required DateTime at,
    bool? locationServicesEnabled,
    bool? internetOnline,
    double? latitude,
    double? longitude,
  }) async {
    final created = <PresenceEvent>[];

    Future<void> record(String type) async {
      final event = PresenceEvent(
        localUuid: (newUuid ?? _fallbackUuid)(),
        attendanceId: attendanceId,
        type: type,
        recordedAt: at.toUtc().toIso8601String(),
        latitude: latitude,
        longitude: longitude,
      );
      await store.add(event);
      created.add(event);
    }

    if (locationServicesEnabled != null) {
      final previous = store.lastLocationServicesEnabled;
      if (previous == null) {
        if (!locationServicesEnabled) await record(PresenceEvent.gpsOff);
      } else if (previous != locationServicesEnabled) {
        await record(
          locationServicesEnabled ? PresenceEvent.gpsOn : PresenceEvent.gpsOff,
        );
      }
      await store.setFlags(locationServicesEnabled: locationServicesEnabled);
    }

    if (internetOnline != null) {
      final previous = store.lastInternetOnline;
      if (previous == null) {
        if (!internetOnline) await record(PresenceEvent.internetOff);
      } else if (previous != internetOnline) {
        await record(
          internetOnline ? PresenceEvent.internetOn : PresenceEvent.internetOff,
        );
      }
      await store.setFlags(internetOnline: internetOnline);
    }

    return created;
  }

  String _fallbackUuid() {
    _sequence += 1;
    return 'presence-$_sequence';
  }
}

/// Uploads the local queue. A failed upload leaves every event in place.
Future<bool> syncPresenceQueue({
  required PresenceEventStore store,
  required int attendanceId,
  required Future<bool> Function(List<PresenceEvent> pending) upload,
}) async {
  final pending = store.pendingFor(attendanceId);
  if (pending.isEmpty) return true;
  final ok = await upload(List<PresenceEvent>.unmodifiable(pending));
  if (!ok) return false;
  await store.markSynced(pending.map((event) => event.localUuid));
  return store.pendingFor(attendanceId).isEmpty;
}
