import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../models/attendance.dart';
import '../models/attendance_format.dart';

class TeamAttendancePulse {
  const TeamAttendancePulse({
    required this.status,
    required this.punchInTime,
    required this.punchOutTime,
    required this.workingDuration,
    this.livePunchIn,
  });

  final String status;
  final String punchInTime;
  final String punchOutTime;
  final String workingDuration;
  final DateTime? livePunchIn;

  static TeamAttendancePulse fromOwn(Attendance? attendance) {
    if (attendance == null || attendance.punchIn == null) {
      return const TeamAttendancePulse(
        status: 'Not Punched In',
        punchInTime: '—',
        punchOutTime: '—',
        workingDuration: '—',
      );
    }
    if (attendance.punchOut != null) {
      return TeamAttendancePulse(
        status: 'Punched Out',
        punchInTime: AttendanceFormat.time(attendance.punchIn),
        punchOutTime: AttendanceFormat.time(attendance.punchOut),
        workingDuration: attendance.workingHours ?? '—',
      );
    }
    return TeamAttendancePulse(
      status: 'Punched In',
      punchInTime: AttendanceFormat.time(attendance.punchIn),
      punchOutTime: '—',
      workingDuration: attendance.workingHours ?? '—',
      livePunchIn: attendance.punchIn,
    );
  }

  static TeamAttendancePulse fromRows(
    List<Map<String, dynamic>> rows, [
    Map<String, dynamic> meta = const {},
  ]) {
    final metaPunchedIn = int.tryParse('${meta['punched_in'] ?? 0}') ?? 0;
    final metaPunchedOut = int.tryParse('${meta['punched_out'] ?? 0}') ?? 0;
    Map<String, dynamic>? working;
    Map<String, dynamic>? completed;
    DateTime? earliestIn;
    DateTime? latestOut;

    for (final row in rows) {
      final punchIn = DateTime.tryParse('${row['punch_in_time'] ?? ''}');
      final punchOut = DateTime.tryParse('${row['punch_out_time'] ?? ''}');
      if (punchIn != null && (earliestIn == null || punchIn.isBefore(earliestIn))) {
        earliestIn = punchIn;
      }
      if (punchOut != null && (latestOut == null || punchOut.isAfter(latestOut))) {
        latestOut = punchOut;
      }
      if (punchIn != null && punchOut == null) {
        working ??= row;
      } else if (punchOut != null) {
        completed ??= row;
      }
    }

    final source = working ?? completed;
    final status = working != null
        ? 'Punched In'
        : (completed != null || metaPunchedOut > 0
            ? 'Punched Out'
            : (metaPunchedIn > 0 ? 'Punched In' : 'Not Punched In'));
    return TeamAttendancePulse(
      status: status,
      punchInTime: _formatTime(source?['punch_in_time'] ?? earliestIn),
      punchOutTime: _formatTime(source?['punch_out_time'] ?? latestOut),
      workingDuration: _duration(source) ?? '—',
    );
  }

  static String _formatTime(Object? value) {
    if (value == null) return '—';
    if (value is DateTime) {
      return DateFormat('hh:mm a').format(value.toLocal());
    }
    final parsed = DateTime.tryParse('$value');
    if (parsed == null) return '—';
    return DateFormat('hh:mm a').format(parsed.toLocal());
  }

  static String? _duration(Map<String, dynamic>? row) {
    if (row == null) return null;
    final minutes = int.tryParse('${row['total_working_minutes'] ?? ''}');
    if (minutes != null && minutes >= 0) {
      return '${minutes ~/ 60}h ${(minutes % 60).toString().padLeft(2, '0')}m';
    }
    final hours = '${row['working_hours'] ?? ''}'.trim();
    return hours.isEmpty ? null : hours;
  }
}

class AttendanceStatusCard extends StatefulWidget {
  const AttendanceStatusCard({
    super.key,
    required this.onDetails,
    required this.punchedIn,
    required this.punchedOut,
    this.pulse,
    this.ownAttendance,
  });

  final TeamAttendancePulse? pulse;
  final Attendance? ownAttendance;
  final int punchedIn;
  final int punchedOut;
  final VoidCallback onDetails;

  @override
  State<AttendanceStatusCard> createState() => _AttendanceStatusCardState();
}

class _AttendanceStatusCardState extends State<AttendanceStatusCard> {
  Timer? _timer;
  String _duration = '—';

  @override
  void initState() {
    super.initState();
    _syncTimer();
  }

  @override
  void didUpdateWidget(covariant AttendanceStatusCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.pulse?.livePunchIn != widget.pulse?.livePunchIn ||
        oldWidget.pulse?.workingDuration != widget.pulse?.workingDuration ||
        oldWidget.ownAttendance?.punchIn != widget.ownAttendance?.punchIn ||
        oldWidget.ownAttendance?.punchOut != widget.ownAttendance?.punchOut) {
      _syncTimer();
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _syncTimer() {
    _timer?.cancel();
    final liveFrom = widget.pulse?.livePunchIn;
    if (liveFrom != null) {
      _duration = _liveDuration(liveFrom);
      _timer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (!mounted) return;
        setState(() => _duration = _liveDuration(liveFrom));
      });
      return;
    }
    _duration = widget.pulse?.workingDuration ?? '—';
  }

  String _liveDuration(DateTime punchIn) {
    final elapsed = AttendanceFormat.istNow().difference(punchIn);
    final totalSeconds = elapsed.isNegative ? 0 : elapsed.inSeconds;
    final hours = totalSeconds ~/ 3600;
    final minutes = (totalSeconds % 3600) ~/ 60;
    return '${hours}h ${minutes.toString().padLeft(2, '0')}m';
  }

  @override
  Widget build(BuildContext context) {
    final pulse = widget.pulse;
    final punchedIn = widget.punchedIn;
    final punchedOut = widget.punchedOut;
    final onDetails = widget.onDetails;
    final status = pulse?.status ??
        (punchedIn > punchedOut
            ? 'Punched In'
            : (punchedOut > 0 ? 'Punched Out' : 'Not Punched In'));
    final Color color;
    final IconData icon;
    if (status == 'Punched Out') {
      color = AppColors.success;
      icon = Icons.verified_rounded;
    } else if (status == 'Punched In') {
      color = AppColors.primary;
      icon = Icons.check_circle_rounded;
    } else {
      color = AppColors.warning;
      icon = Icons.fingerprint_rounded;
    }

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        onTap: onDetails,
        borderRadius: BorderRadius.circular(22),
        child: Ink(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0F0F172A),
                blurRadius: 18,
                offset: Offset(0, 6),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
            child: Column(
              children: [
                Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Icon(icon, color: color, size: 22),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Attendance Status',
                            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.textSecondary,
                                ),
                          ),
                          Text(
                            status,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
                        ],
                      ),
                    ),
                    TextButton(
                      onPressed: onDetails,
                      child: const Text('View Details'),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    _MetaChip(
                      label: 'Punch In',
                      value: pulse?.punchInTime ?? '—',
                      background: const Color(0xFFE8F1FF),
                    ),
                    const SizedBox(width: 8),
                    _MetaChip(
                      label: 'Punch Out',
                      value: pulse?.punchOutTime ?? '—',
                      background: const Color(0xFFFDE8EF),
                    ),
                    const SizedBox(width: 8),
                    _MetaChip(
                      label: 'Duration',
                      value: _duration,
                      background: const Color(0xFFEDE9FE),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({
    required this.label,
    required this.value,
    required this.background,
  });

  final String label;
  final String value;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    fontSize: 10,
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
            ),
            const SizedBox(height: 4),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}
