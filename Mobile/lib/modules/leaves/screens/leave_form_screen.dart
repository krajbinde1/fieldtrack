import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../api/leave_api.dart';
import '../models/leave.dart';

class LeaveFormScreen extends StatefulWidget {
  const LeaveFormScreen({super.key, this.leaveId});

  final int? leaveId;

  @override
  State<LeaveFormScreen> createState() => _LeaveFormScreenState();
}

class _LeaveFormScreenState extends State<LeaveFormScreen> {
  final _reason = TextEditingController();
  LeaveApi? _api;
  bool _loading = false;
  bool _busy = false;
  String? _error;
  String? _leaveType = 'casual';
  DateTime? _from;
  DateTime? _to;
  String? _filePath;
  String? _fileName;
  String? _existingDocument;

  int get _days {
    if (_from == null || _to == null || _to!.isBefore(_from!)) return 0;
    return _to!.difference(_from!).inDays + 1;
  }

  @override
  void initState() {
    super.initState();
    if (widget.leaveId != null) {
      _loading = true;
      _load();
    }
  }

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      _api ??= await LeaveApi.create();
      final record = await _api!.show(widget.leaveId!);
      if (!mounted) return;
      setState(() {
        _leaveType = record.leaveType;
        _from = DateTime.tryParse(record.fromDate);
        _to = DateTime.tryParse(record.toDate);
        _reason.text = record.reason;
        _existingDocument = record.documentName;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = '$error';
        _loading = false;
      });
    }
  }

  Future<void> _pickDate({required bool from}) async {
    final initial = from ? (_from ?? DateTime.now()) : (_to ?? _from ?? DateTime.now());
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked == null) return;
    setState(() {
      if (from) {
        _from = picked;
        if (_to != null && _to!.isBefore(picked)) _to = picked;
      } else {
        _to = picked;
      }
    });
  }

  Future<void> _pickFile() async {
    final files = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    if (files.isEmpty) return;
    var path = files.first.path;
    if (path == null) {
      final bytes = await files.first.readAsBytes();
      final tmp = File('${Directory.systemTemp.path}/${files.first.name}');
      await tmp.writeAsBytes(bytes, flush: true);
      path = tmp.path;
    }
    setState(() {
      _filePath = path;
      _fileName = files.first.name;
    });
  }

  Future<void> _submit() async {
    if (_leaveType == null) {
      _toast('Please select a leave type.');
      return;
    }
    if (_from == null || _to == null) {
      _toast('Please select from and to dates.');
      return;
    }
    if (_reason.text.trim().isEmpty) {
      _toast('Please enter a reason.');
      return;
    }
    setState(() => _busy = true);
    try {
      _api ??= await LeaveApi.create();
      final payload = {
        'leave_type': _leaveType,
        'from_date': DateFormat('yyyy-MM-dd').format(_from!),
        'to_date': DateFormat('yyyy-MM-dd').format(_to!),
        'reason': _reason.text.trim(),
      };
      if (widget.leaveId == null) {
        await _api!.apply(payload, filePath: _filePath);
      } else {
        await _api!.update(widget.leaveId!, payload, filePath: _filePath);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            widget.leaveId == null ? 'Leave applied.' : 'Leave updated.',
          ),
        ),
      );
      context.go('/leaves/mine');
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      _toast('$error');
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: widget.leaveId == null ? 'Apply Leave' : 'Edit Leave',
      showBack: true,
      body: _loading
          ? const PgLoadingState()
          : _error != null
          ? PgErrorState(message: _error!, onRetry: _load)
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                PgCard(
                  child: Column(
                    children: [
                      DropdownButtonFormField<String>(
                        key: ValueKey(_leaveType),
                        initialValue: _leaveType,
                        decoration: const InputDecoration(labelText: 'Leave Type'),
                        items: [
                          for (final option in leaveTypeOptions)
                            DropdownMenuItem(
                              value: option.$1,
                              child: Text(option.$2),
                            ),
                        ],
                        onChanged: (value) => setState(() => _leaveType = value),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      _dateField('From Date', _from, () => _pickDate(from: true)),
                      const SizedBox(height: AppSpacing.md),
                      _dateField('To Date', _to, () => _pickDate(from: false)),
                      const SizedBox(height: AppSpacing.md),
                      InputDecorator(
                        decoration: const InputDecoration(labelText: 'Total Days'),
                        child: Text('$_days'),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      TextField(
                        controller: _reason,
                        maxLines: 4,
                        decoration: const InputDecoration(labelText: 'Reason'),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          _fileName ?? _existingDocument ?? 'No supporting document',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                      const SizedBox(height: 8),
                      OutlinedButton.icon(
                        onPressed: _pickFile,
                        icon: const Icon(Icons.attach_file_rounded),
                        label: const Text('Attach image or PDF (optional)'),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(widget.leaveId == null ? 'Submit Request' : 'Update Request'),
                ),
              ],
            ),
    );
  }

  Widget _dateField(String label, DateTime? value, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      child: InputDecorator(
        decoration: InputDecoration(labelText: label),
        child: Text(
          value == null ? 'Select date' : DateFormat('d MMM yyyy').format(value),
          style: TextStyle(
            color: value == null ? AppColors.textMuted : AppColors.textPrimary,
          ),
        ),
      ),
    );
  }
}
