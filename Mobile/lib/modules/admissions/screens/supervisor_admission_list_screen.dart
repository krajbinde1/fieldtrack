import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/admission_api.dart';
import '../models/admission.dart';

class SupervisorAdmissionListScreen extends StatefulWidget {
  const SupervisorAdmissionListScreen({
    super.key,
    required this.auth,
    required this.apiPrefix,
  });

  final AuthController auth;
  final String apiPrefix;

  @override
  State<SupervisorAdmissionListScreen> createState() =>
      _SupervisorAdmissionListScreenState();
}

class _SupervisorAdmissionListScreenState
    extends State<SupervisorAdmissionListScreen> {
  late Future<List<AdmissionRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<AdmissionRecord>> _load() async {
    return (await AdmissionApi.create()).supervisorList(widget.apiPrefix);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    final prefix = widget.apiPrefix == 'director' ? '/director' : '/manager';
    return PgPageScaffold(
      title: 'Admissions',
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
                children: const [
                  SizedBox(height: 80),
                  PgEmptyState(
                    icon: Icon(Icons.how_to_reg_rounded),
                    message: 'No admissions in your assigned center(s).',
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
                  onTap: () => context.push('$prefix/admissions/${item.id}'),
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
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          PgStatusBadge(
                            label: item.statusLabel,
                            tone: item.isSubmitted
                                ? PgStatusTone.approved
                                : PgStatusTone.pending,
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        [
                          item.employeeName,
                          item.centerName,
                          item.schemeName ?? item.projectName,
                        ].where((part) => (part ?? '').trim().isNotEmpty).join(' · '),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                      if (item.updatedAt != null)
                        Text(
                          _fmt(item.updatedAt!),
                          style: Theme.of(context).textTheme.bodySmall,
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

  String _fmt(String raw) {
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return raw;
    return DateFormat('d MMM yyyy').format(parsed);
  }
}
