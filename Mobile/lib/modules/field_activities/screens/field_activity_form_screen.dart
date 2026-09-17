import 'dart:io';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../attendance/models/attendance_format.dart';
import '../api/field_activity_api.dart';
import '../repository/field_activity_capture.dart';

class FieldActivityFormScreen extends StatefulWidget {
  const FieldActivityFormScreen({super.key});

  @override
  State<FieldActivityFormScreen> createState() => _FieldActivityFormScreenState();
}

class _FieldActivityFormScreenState extends State<FieldActivityFormScreen> {
  final _remarks = TextEditingController();
  final _customName = TextEditingController();
  final _capture = FieldActivityCapture();
  FieldActivityApi? _api;
  Map<String, String> _types = const {
    'village_visit': 'Village Visit',
    'community_meeting': 'Community Meeting',
    'awareness_camp': 'Awareness Camp',
    'follow_up': 'Follow-up',
    'household_survey': 'Household Survey',
    'other': 'Other',
  };
  String _type = 'village_visit';
  DateTime _activityAt = AttendanceFormat.istNow();
  FieldActivityCaptureResult? _captureResult;
  bool _loadingTypes = true;
  bool _capturing = false;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadTypes();
  }

  @override
  void dispose() {
    _remarks.dispose();
    _customName.dispose();
    super.dispose();
  }

  Future<void> _loadTypes() async {
    try {
      _api ??= await FieldActivityApi.create();
      final types = await _api!.types();
      if (!mounted) return;
      setState(() {
        if (types.isNotEmpty) _types = types;
        _loadingTypes = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingTypes = false);
    }
  }

  String get _activityName {
    if (_type == 'other') {
      final custom = _customName.text.trim();
      return custom.isEmpty ? 'Other' : custom;
    }
    return _types[_type] ?? 'Activity';
  }

  Future<void> _takePhoto() async {
    setState(() {
      _capturing = true;
      _error = null;
    });
    try {
      final result = await _capture.capture();
      if (!mounted) return;
      setState(() {
        _captureResult = result;
        _activityAt = result.capturedAt;
        _capturing = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _capturing = false;
        _error = '$error';
      });
    }
  }

  Future<void> _submit() async {
    final capture = _captureResult;
    if (capture == null) {
      _toast('Please capture a camera photo with GPS.');
      return;
    }
    if (_type == 'other' && _customName.text.trim().isEmpty) {
      _toast('Please enter the activity name.');
      return;
    }
    setState(() => _busy = true);
    try {
      _api ??= await FieldActivityApi.create();
      await _api!.submit({
        'activity_type': _type,
        'activity_name': _activityName,
        'activity_at': capture.capturedAt.toIso8601String(),
        'remarks': _remarks.text.trim(),
        'latitude': capture.latitude.toString(),
        'longitude': capture.longitude.toString(),
        'location': capture.location,
      }, capture.photoPath);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Activity submitted.')),
      );
      context.go('/field-activities/mine');
    } catch (error) {
      if (!mounted) return;
      _toast('$error');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final capture = _captureResult;
    return PgPageScaffold(
      title: 'Add Activity',
      showBack: true,
      body: _loadingTypes
          ? const PgLoadingState()
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                PgCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      DropdownButtonFormField<String>(
                        key: ValueKey(_type),
                        initialValue: _types.containsKey(_type) ? _type : _types.keys.first,
                        decoration: const InputDecoration(
                          labelText: 'Activity Name / Type',
                        ),
                        items: [
                          for (final entry in _types.entries)
                            DropdownMenuItem(
                              value: entry.key,
                              child: Text(entry.value),
                            ),
                        ],
                        onChanged: (value) {
                          if (value == null) return;
                          setState(() => _type = value);
                        },
                      ),
                      if (_type == 'other') ...[
                        const SizedBox(height: AppSpacing.md),
                        TextField(
                          controller: _customName,
                          decoration: const InputDecoration(
                            labelText: 'Activity Name',
                          ),
                        ),
                      ],
                      const SizedBox(height: AppSpacing.md),
                      InputDecorator(
                        decoration: const InputDecoration(
                          labelText: 'Activity Date & Time',
                        ),
                        child: Text(
                          DateFormat('d MMM yyyy, hh:mm a').format(_activityAt),
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      TextField(
                        controller: _remarks,
                        maxLines: 4,
                        decoration: const InputDecoration(
                          labelText: 'Remarks',
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                PgCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Photo (camera only)',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      if (capture != null)
                        ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: Image.file(
                            File(capture.photoPath),
                            height: 180,
                            width: double.infinity,
                            fit: BoxFit.cover,
                          ),
                        )
                      else
                        Text(
                          'A live camera photo is mandatory. Gallery upload is not allowed.',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      const SizedBox(height: AppSpacing.md),
                      OutlinedButton.icon(
                        onPressed: _capturing || _busy ? null : _takePhoto,
                        icon: _capturing
                            ? const SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Icon(Icons.photo_camera_outlined),
                        label: Text(
                          capture == null ? 'Capture Photo' : 'Retake Photo',
                        ),
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          _error!,
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: AppColors.error,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                PgCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Location',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        capture?.location ??
                            'GPS will be captured automatically with the photo.',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      if (capture != null) ...[
                        const SizedBox(height: 6),
                        Text(
                          'Lat ${capture.latitude.toStringAsFixed(6)}, Lng ${capture.longitude.toStringAsFixed(6)}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                FilledButton(
                  onPressed: _busy || _capturing ? null : _submit,
                  child: _busy
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Submit Activity'),
                ),
              ],
            ),
    );
  }
}
