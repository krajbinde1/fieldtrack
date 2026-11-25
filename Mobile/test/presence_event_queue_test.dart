import 'package:fieldtrack/modules/attendance/route_tracking/presence_event.dart';
import 'package:fieldtrack/modules/attendance/route_tracking/presence_event_store.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('GPS off stored while offline is synced later and not lost', () async {
    final store = MemoryPresenceEventStore();
    var sequence = 0;
    final queue = PresenceEventQueue(store, newUuid: () => 'event-${sequence++}');
    final at = DateTime.utc(2026, 11, 25, 10);

    final first = await queue.apply(
      attendanceId: 7,
      at: at,
      locationServicesEnabled: true,
      internetOnline: true,
      latitude: 18.52,
      longitude: 73.85,
    );
    expect(first, isEmpty, reason: 'baseline GPS on and online is not an event');

    final stored = await queue.apply(
      attendanceId: 7,
      at: at.add(const Duration(minutes: 1)),
      locationServicesEnabled: false,
      internetOnline: false,
      latitude: 18.521,
      longitude: 73.851,
    );
    expect(stored.map((event) => event.type), [
      PresenceEvent.gpsOff,
      PresenceEvent.internetOff,
    ]);
    expect(stored.first.latitude, 18.521);
    expect(stored.first.longitude, 73.851);

    await queue.apply(
      attendanceId: 7,
      at: at.add(const Duration(minutes: 2)),
      locationServicesEnabled: false,
      internetOnline: false,
    );
    expect(store.pendingFor(7), hasLength(2));

    final beforeSync = store.pendingFor(7).map((event) => event.localUuid).toList();
    final failed = await syncPresenceQueue(
      store: store,
      attendanceId: 7,
      upload: (_) async => false,
    );
    expect(failed, isFalse);
    expect(
      store.pendingFor(7).map((event) => event.localUuid).toList(),
      beforeSync,
    );

    List<PresenceEvent>? uploaded;
    final synced = await syncPresenceQueue(
      store: store,
      attendanceId: 7,
      upload: (pending) async {
        uploaded = pending;
        return true;
      },
    );
    expect(synced, isTrue);
    expect(uploaded, isNotNull);
    expect(uploaded!.map((event) => event.localUuid).toList(), beforeSync);
    expect(store.pendingFor(7), isEmpty);
  });

  test('internet restored queues a new event without dropping GPS off', () async {
    final store = MemoryPresenceEventStore();
    var sequence = 0;
    final queue = PresenceEventQueue(store, newUuid: () => 'event-${sequence++}');
    final at = DateTime.utc(2026, 11, 25, 11);

    await queue.apply(
      attendanceId: 4,
      at: at,
      locationServicesEnabled: false,
      internetOnline: false,
      latitude: 19.0,
      longitude: 72.8,
    );
    await queue.apply(
      attendanceId: 4,
      at: at.add(const Duration(minutes: 3)),
      locationServicesEnabled: false,
      internetOnline: true,
    );

    final types = store.pendingFor(4).map((event) => event.type).toList();
    expect(types, [
      PresenceEvent.gpsOff,
      PresenceEvent.internetOff,
      PresenceEvent.internetOn,
    ]);

    await syncPresenceQueue(
      store: store,
      attendanceId: 4,
      upload: (_) async => true,
    );
    expect(store.pendingFor(4), isEmpty);
  });
}
