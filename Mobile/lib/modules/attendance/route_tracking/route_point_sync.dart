import 'models/route_point.dart';
import 'presence_cycle.dart';
import 'presence_event_store.dart';
import 'route_point_api.dart';
import 'route_point_store.dart';
import 'route_tracking_log.dart';

typedef InvalidAttendanceHandler =
    Future<void> Function(int attendanceId, String message);

class RoutePointSync {
  RoutePointSync(this._store, this._api, {this.onInvalidAttendance});

  static const batchSize = 50;
  static const invalidAttendanceMessage =
      'Route points can only be submitted for an active punch-in session';

  final RoutePointStore _store;
  final RoutePointApi _api;
  final InvalidAttendanceHandler? onInvalidAttendance;
  Future<void>? _inFlight;

  Future<void> syncPending({
    int? activeAttendanceId,
    bool allowClosedAttendance = false,
  }) async {
    if (_inFlight != null) return _inFlight!;
    _inFlight = _syncPendingInternal(
      activeAttendanceId: activeAttendanceId,
      allowClosedAttendance: allowClosedAttendance,
    );
    try {
      await _inFlight;
    } finally {
      _inFlight = null;
    }
  }

  bool _isInvalidAttendanceError(String message) {
    final lower = message.toLowerCase();
    return lower.contains('active punch-in session') ||
        lower.contains('does not belong to you') ||
        lower.contains('not configured') ||
        lower.contains('outside the attendance session') ||
        lower.contains('no punch-in');
  }

  Future<void> _syncPendingInternal({
    int? activeAttendanceId,
    bool allowClosedAttendance = false,
  }) async {
    try {
      await _prefsReload();
      await _tickPresence();
    } catch (error) {
      routeTrackingLog('Presence tick failed (route sync continues): $error');
    }

    var pending = _store.pendingPoints();
    if (pending.isEmpty) {
      routeTrackingLog('Sync skipped: no pending route points');
      return;
    }

    routeTrackingLog(
      'Sync starting: ${pending.length} pending point(s), '
      'activeAttendanceId=$activeAttendanceId '
      'allowClosed=$allowClosedAttendance',
    );

    if (activeAttendanceId != null &&
        activeAttendanceId > 0 &&
        !allowClosedAttendance) {
      final stale = pending
          .where((point) => point.attendanceId != activeAttendanceId)
          .map((point) => point.attendanceId)
          .toSet();
      if (stale.isNotEmpty) {
        routeTrackingLog(
          'Removing pending points for stale attendance_id(s): $stale '
          '(active=$activeAttendanceId)',
        );
        await _store.retainOnlyAttendance(activeAttendanceId);
        pending = _store.pendingPoints();
      }
    }

    if (pending.isEmpty) {
      routeTrackingLog('Sync skipped: no points for active attendance');
      return;
    }

    pending.sort((a, b) => a.recordedAt.compareTo(b.recordedAt));

    final grouped = <int, List<RoutePoint>>{};
    for (final point in pending) {
      grouped.putIfAbsent(point.attendanceId, () => []).add(point);
    }

    for (final entry in grouped.entries) {
      final uploadAttendanceId = entry.key;
      routeTrackingLog(
        'Preparing upload: uploadedAttendanceId=$uploadAttendanceId '
        'activeAttendanceId=$activeAttendanceId',
      );

      if (!allowClosedAttendance &&
          activeAttendanceId != null &&
          activeAttendanceId > 0 &&
          uploadAttendanceId != activeAttendanceId) {
        routeTrackingLog(
          'Skipping upload for mismatched attendance_id=$uploadAttendanceId',
        );
        await _store.clearPointsForAttendance(uploadAttendanceId);
        continue;
      }

      final points = entry.value;
      var offset = 0;
      while (offset < points.length) {
        final batch = points.skip(offset).take(batchSize).toList();
        routeTrackingLog(
          'Uploading batch: uploadedAttendanceId=$uploadAttendanceId '
          'activeAttendanceId=$activeAttendanceId count=${batch.length}',
        );
        try {
          final presenceStore = SharedPrefsPresenceEventStore(
            _store.preferences,
          );
          final presenceEvents = presenceStore
              .pendingFor(uploadAttendanceId)
              .map((event) => event.toApi())
              .toList();
          await _api.uploadBatch(
            attendanceId: uploadAttendanceId,
            activeAttendanceId: activeAttendanceId,
            points: batch,
            presenceEvents: presenceEvents,
          );
          await _store.markSynced(batch.map((point) => point.localUuid));
          await presenceStore.markSynced(
            presenceEvents.map((event) => '${event['local_uuid']}'),
          );
          routeTrackingLog(
            'Upload success: removed ${batch.length} synced point(s) from queue',
          );
        } on RoutePointApiException catch (error) {
          if (_isInvalidAttendanceError(error.message)) {
            routeTrackingLog(
              'Invalid attendance upload rejected: attendanceId=$uploadAttendanceId '
              'message=${error.message}',
            );
            await _store.clearPointsForAttendance(uploadAttendanceId);
            await onInvalidAttendance?.call(uploadAttendanceId, error.message);
            break;
          }
          // Keep points queued for auto-retry when offline / transient errors.
          routeTrackingLog(
            'Upload failed — keeping points queued: ${error.message}',
          );
          break;
        } catch (error) {
          routeTrackingLog(
            'Upload failed — keeping points queued: $error',
          );
          break;
        }
        offset += batch.length;
      }
    }
  }

  Future<void> _prefsReload() async {
    try {
      await _store.preferences.reload();
    } catch (_) {}
  }

  /// Heartbeat while punched in. After punch out, only flush events already stored.
  Future<void> _tickPresence() async {
    final session = _store.session;
    final presenceStore = SharedPrefsPresenceEventStore(_store.preferences);
    final cycle = PresenceCycle(
      store: presenceStore,
      upload: _api.uploadHeartbeat,
    );
    if (session != null && session.isActive && session.attendanceId > 0) {
      await cycle.tick(
        attendanceId: session.attendanceId,
        lastLatitude: session.lastLatitude,
        lastLongitude: session.lastLongitude,
      );
      return;
    }

    final attendanceIds = presenceStore
        .pending()
        .map((event) => event.attendanceId)
        .toSet();
    for (final attendanceId in attendanceIds) {
      await cycle.flush(attendanceId: attendanceId, heartbeat: false);
    }
  }
}
