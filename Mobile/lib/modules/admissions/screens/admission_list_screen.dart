import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../api/admission_api.dart';
import '../models/admission.dart';

class AdmissionListScreen extends StatefulWidget {
  const AdmissionListScreen({super.key, required this.drafts});

  final bool drafts;

  @override
  State<AdmissionListScreen> createState() => _AdmissionListScreenState();
}

class _AdmissionListScreenState extends State<AdmissionListScreen> {
  AdmissionApi? _api;
  late Future<List<AdmissionRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<AdmissionRecord>> _load() async {
    _api ??= await AdmissionApi.create();
    return widget.drafts ? _api!.drafts() : _api!.submitted();
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _delete(AdmissionRecord record) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete draft?'),
        content: Text(
          'This will remove the draft for ${record.displayName.isEmpty ? 'this applicant' : record.displayName}.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await _api!.deleteDraft(record.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Draft deleted.')),
      );
      await _refresh();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$error')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: widget.drafts ? 'Draft Admissions' : 'Submitted Admissions',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<AdmissionRecord>>(
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
            final items = snapshot.data ?? const <AdmissionRecord>[];
            if (items.isEmpty) {
              return ListView(
                children: [
                  const SizedBox(height: 80),
                  PgEmptyState(
                    icon: Icon(
                      widget.drafts
                          ? Icons.drafts_outlined
                          : Icons.task_alt_rounded,
                    ),
                    message: widget.drafts
                        ? 'No draft admissions yet.'
                        : 'No submitted admissions yet.',
                    actionLabel: 'New Admission',
                    onAction: () => context.push('/admissions/new'),
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
              itemBuilder: (context, index) {
                final item = items[index];
                return PgCard(
                  onTap: () => context.push('/admissions/${item.id}'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              item.displayName.isEmpty
                                  ? 'Untitled admission'
                                  : item.displayName,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          PgStatusBadge(
                            label: item.statusLabel,
                            tone: item.statusTone,
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        item.schemeName ?? 'Scheme not selected',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                      if ((item.reviewReason ?? '').trim().isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Text(
                          item.isRejected
                              ? 'Rejected: ${item.reviewReason}'
                              : 'Reverted: ${item.reviewReason}',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: item.isRejected
                                    ? AppColors.error
                                    : AppColors.warning,
                              ),
                        ),
                      ],
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Text(
                            _dateLabel(item),
                            style: Theme.of(context).textTheme.labelSmall
                                ?.copyWith(color: AppColors.textMuted),
                          ),
                          const Spacer(),
                          if (widget.drafts && item.isDraft)
                            IconButton(
                              onPressed: () => _delete(item),
                              icon: const Icon(Icons.delete_outline_rounded),
                              color: AppColors.error,
                            ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }

  String _dateLabel(AdmissionRecord item) {
    final raw = item.submittedAt ?? item.updatedAt;
    if (raw == null || raw.isEmpty) return '';
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return raw;
    return DateFormat('d MMM yyyy, h:mm a').format(parsed.toLocal());
  }
}
