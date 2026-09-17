import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/leave_api.dart';
import '../models/leave.dart';

class SupervisorLeaveDetailScreen extends StatefulWidget {
  const SupervisorLeaveDetailScreen({
    super.key,
    required this.auth,
    required this.apiPrefix,
    required this.leaveId,
  });

  final AuthController auth;
  final String apiPrefix;
  final int leaveId;

  @override
  State<SupervisorLeaveDetailScreen> createState() =>
      _SupervisorLeaveDetailScreenState();
}

class _SupervisorLeaveDetailScreenState
    extends State<SupervisorLeaveDetailScreen> {
  LeaveApi? _api;
  late Future<LeaveRecord> _future;
  bool _busy = false;

  bool get _canAct =>
      widget.auth.userRole.isCenterManager ||
      ((widget.auth.userRole.isDirector || widget.auth.userRole.isAdmin) &&
          widget.apiPrefix == 'director');

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<LeaveRecord> _load() async {
    _api ??= await LeaveApi.create();
    return _api!.supervisorShow(widget.apiPrefix, widget.leaveId);
  }

  Future<void> _approve(LeaveRecord record) async {
    final remark = await _prompt('Approval remark (optional)', required: false);
    if (remark == null) return;
    setState(() => _busy = true);
    try {
      await _api!.approve(
        record.id,
        remark: remark.isEmpty ? null : remark,
        prefix: widget.apiPrefix,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Leave approved.')),
      );
      setState(() {
        _busy = false;
        _future = _load();
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  Future<void> _reject(LeaveRecord record) async {
    final remark = await _prompt('Rejection remark', required: true);
    if (remark == null) return;
    if (remark.trim().isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Rejection remark is required.')),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await _api!.reject(record.id, remark.trim(), prefix: widget.apiPrefix);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Leave rejected.')),
      );
      setState(() {
        _busy = false;
        _future = _load();
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  Future<String?> _prompt(String title, {required bool required}) async {
    final controller = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: InputDecoration(
            hintText: required ? 'Enter remark' : 'Optional remark',
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text),
            child: const Text('Continue'),
          ),
        ],
      ),
    );
    controller.dispose();
    return result;
  }

  Future<void> _preview(LeaveRecord record) async {
    try {
      final bytes = await _api!.supervisorDownload(widget.apiPrefix, record.id);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/${record.documentName ?? 'leave-document'}');
      await file.writeAsBytes(Uint8List.fromList(bytes), flush: true);
      await OpenFilex.open(file.path);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Leave Request',
      showBack: true,
      body: FutureBuilder<LeaveRecord>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const PgLoadingState();
          }
          if (snapshot.hasError) {
            return PgErrorState(
              message: '${snapshot.error}',
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final item = snapshot.data!;
          return ListView(
            padding: const EdgeInsets.all(AppSpacing.screenPadding),
            children: [
              PgCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            item.employeeName ?? 'Employee',
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                        ),
                        PgStatusBadge(
                          label: item.statusLabel,
                          tone: item.isApproved
                              ? PgStatusTone.approved
                              : item.isRejected
                              ? PgStatusTone.rejected
                              : PgStatusTone.pending,
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _row('Leave Type', item.leaveTypeLabel),
                    _row('From', _fmt(item.fromDate)),
                    _row('To', _fmt(item.toDate)),
                    _row('Total Days', '${item.totalDays}'),
                    _row('Reason', item.reason),
                    if (item.projectName != null) _row('Project', item.projectName!),
                    if (item.centerName != null) _row('Center', item.centerName!),
                    if (item.approvalRemark != null && item.approvalRemark!.isNotEmpty)
                      _row('Approval remark', item.approvalRemark!),
                    if (item.rejectionRemark != null && item.rejectionRemark!.isNotEmpty)
                      _row('Rejection remark', item.rejectionRemark!),
                    if (item.hasDocument)
                      TextButton.icon(
                        onPressed: () => _preview(item),
                        icon: const Icon(Icons.visibility_outlined),
                        label: Text(item.documentName ?? 'View document'),
                      ),
                  ],
                ),
              ),
              if (_canAct && item.isPending) ...[
                const SizedBox(height: AppSpacing.md),
                FilledButton(
                  onPressed: _busy ? null : () => _approve(item),
                  child: const Text('Approve'),
                ),
                const SizedBox(height: AppSpacing.sm),
                OutlinedButton(
                  onPressed: _busy ? null : () => _reject(item),
                  child: const Text('Reject'),
                ),
              ],
            ],
          );
        },
      ),
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.labelSmall?.copyWith(color: AppColors.textMuted)),
          Text(value, style: Theme.of(context).textTheme.titleSmall),
        ],
      ),
    );
  }

  String _fmt(String raw) {
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return raw;
    return DateFormat('d MMM yyyy').format(parsed);
  }
}
