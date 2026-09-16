import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../api/leave_api.dart';
import '../models/leave.dart';

class LeaveDetailScreen extends StatefulWidget {
  const LeaveDetailScreen({super.key, required this.leaveId});

  final int leaveId;

  @override
  State<LeaveDetailScreen> createState() => _LeaveDetailScreenState();
}

class _LeaveDetailScreenState extends State<LeaveDetailScreen> {
  LeaveApi? _api;
  late Future<LeaveRecord> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<LeaveRecord> _load() async {
    _api ??= await LeaveApi.create();
    return _api!.show(widget.leaveId);
  }

  Future<void> _cancel(LeaveRecord record) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel leave?'),
        content: const Text('This pending leave request will be cancelled.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('No')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Cancel leave')),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await _api!.cancel(record.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Leave cancelled.')),
      );
      context.go('/leaves/mine');
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  Future<void> _preview(LeaveRecord record) async {
    try {
      final bytes = await _api!.download(record.id);
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
      title: 'Leave Details',
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
                            item.leaveTypeLabel,
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
                    _row('From', _fmt(item.fromDate)),
                    _row('To', _fmt(item.toDate)),
                    _row('Total Days', '${item.totalDays}'),
                    _row('Reason', item.reason),
                    if (item.approvalRemark != null && item.approvalRemark!.isNotEmpty)
                      _row('Approval remark', item.approvalRemark!),
                    if (item.rejectionRemark != null && item.rejectionRemark!.isNotEmpty)
                      _row('Rejection remark', item.rejectionRemark!),
                    if (item.hasDocument) ...[
                      const SizedBox(height: 8),
                      TextButton.icon(
                        onPressed: () => _preview(item),
                        icon: const Icon(Icons.visibility_outlined),
                        label: Text(item.documentName ?? 'View document'),
                      ),
                    ],
                  ],
                ),
              ),
              if (item.editable) ...[
                const SizedBox(height: AppSpacing.md),
                FilledButton(
                  onPressed: () => context.push('/leaves/${item.id}/edit'),
                  child: const Text('Edit'),
                ),
                const SizedBox(height: AppSpacing.sm),
                OutlinedButton(
                  onPressed: () => _cancel(item),
                  child: const Text('Cancel Leave'),
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
