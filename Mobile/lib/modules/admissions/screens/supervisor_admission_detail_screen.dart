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
import '../api/admission_api.dart';
import '../models/admission.dart';

class SupervisorAdmissionDetailScreen extends StatefulWidget {
  const SupervisorAdmissionDetailScreen({
    super.key,
    required this.auth,
    required this.apiPrefix,
    required this.admissionId,
  });

  final AuthController auth;
  final String apiPrefix;
  final int admissionId;

  @override
  State<SupervisorAdmissionDetailScreen> createState() =>
      _SupervisorAdmissionDetailScreenState();
}

class _SupervisorAdmissionDetailScreenState
    extends State<SupervisorAdmissionDetailScreen> {
  AdmissionApi? _api;
  late Future<AdmissionRecord> _future;
  bool _busy = false;

  bool get _canAct =>
      widget.auth.userRole.isCenterManager && widget.apiPrefix != 'director';

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<AdmissionRecord> _load() async {
    _api ??= await AdmissionApi.create();
    return _api!.supervisorShow(widget.apiPrefix, widget.admissionId);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _confirm(AdmissionRecord record) async {
    setState(() => _busy = true);
    try {
      await _api!.confirm(record.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Admission confirmed.')),
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

  Future<void> _revert(AdmissionRecord record) async {
    final reason = await _prompt('Revert reason');
    if (reason == null) return;
    if (reason.trim().isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Revert reason is required.')),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await _api!.revert(record.id, reason.trim());
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Admission reverted.')),
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

  Future<void> _reject(AdmissionRecord record) async {
    final reason = await _prompt('Reject reason');
    if (reason == null) return;
    if (reason.trim().isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Reject reason is required.')),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await _api!.reject(record.id, reason.trim());
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Admission rejected.')),
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

  Future<String?> _prompt(String title) async {
    final controller = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: const InputDecoration(hintText: 'Enter reason'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
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

  Future<void> _preview(AdmissionDocumentInfo document) async {
    try {
      final bytes = await _api!.supervisorDownloadDocument(
        prefix: widget.apiPrefix,
        admissionId: widget.admissionId,
        documentId: document.id,
      );
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/${document.originalName}');
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
      title: 'Admission',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<AdmissionRecord>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const PgLoadingState();
            }
            if (snapshot.hasError) {
              return PgErrorState(
                message: '${snapshot.error}',
                onRetry: _refresh,
              );
            }
            final item = snapshot.data;
            if (item == null) {
              return const PgEmptyState(
                icon: Icon(Icons.how_to_reg_rounded),
                message: 'Admission not found.',
              );
            }
            final rows = <(String, String)>[
              ('Status', item.statusLabel),
              ('Employee', item.employeeName ?? '—'),
              ('Center', item.centerName ?? '—'),
              ('Scheme / Project', item.schemeName ?? item.projectName ?? '—'),
              ('Gender', item.gender ?? '—'),
              ('Religion', item.religion ?? '—'),
              ('Caste', item.caste ?? '—'),
              ('Address', item.addressLabel.isEmpty ? '—' : item.addressLabel),
              (
                'Submitted',
                item.submittedAt == null ? '—' : _fmt(item.submittedAt!),
              ),
              if (item.confirmedAt != null)
                (
                  'Confirmed Date',
                  _fmt(item.confirmedAt!),
                ),
              if ((item.confirmedByName ?? '').trim().isNotEmpty)
                ('Confirmed By', item.confirmedByName!),
              if ((item.reviewReason ?? '').trim().isNotEmpty)
                (
                  item.isRejected ? 'Reject reason' : 'Revert reason',
                  item.reviewReason!,
                ),
            ];
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
                              item.displayName.isEmpty
                                  ? 'Admission #${item.id}'
                                  : item.displayName,
                              style: Theme.of(context).textTheme.titleLarge,
                            ),
                          ),
                          PgStatusBadge(
                            label: item.statusLabel,
                            tone: item.statusTone,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                PgCard(
                  padding: EdgeInsets.zero,
                  child: Column(
                    children: rows
                        .map(
                          (row) => ListTile(
                            title: Text(row.$1),
                            subtitle: Text(row.$2),
                          ),
                        )
                        .toList(),
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                PgCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Documents',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      if (item.documents.isEmpty)
                        Text(
                          'No documents uploaded.',
                          style: Theme.of(context).textTheme.bodySmall,
                        )
                      else
                        for (final document in item.documents)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const Icon(Icons.description_outlined),
                            title: Text(document.label),
                            subtitle: Text(document.originalName),
                            trailing: IconButton(
                              onPressed: () => _preview(document),
                              icon: const Icon(Icons.visibility_outlined),
                            ),
                          ),
                    ],
                  ),
                ),
                if (_canAct && item.canReview) ...[
                  const SizedBox(height: AppSpacing.lg),
                  FilledButton(
                    onPressed: _busy ? null : () => _confirm(item),
                    child: const Text('Confirm'),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  OutlinedButton(
                    onPressed: _busy ? null : () => _revert(item),
                    child: const Text('Revert'),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  OutlinedButton(
                    onPressed: _busy ? null : () => _reject(item),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.error,
                    ),
                    child: const Text('Reject'),
                  ),
                ],
              ],
            );
          },
        ),
      ),
    );
  }

  String _fmt(String raw) {
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return raw;
    return DateFormat('d MMM yyyy').format(parsed);
  }
}
