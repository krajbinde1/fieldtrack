import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_client.dart';
import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/director_api.dart';

class DirectorCentersScreen extends StatefulWidget {
  const DirectorCentersScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  State<DirectorCentersScreen> createState() => _DirectorCentersScreenState();
}

class _DirectorCentersScreenState extends State<DirectorCentersScreen> {
  late final DirectorApi _api;
  final _search = TextEditingController();
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _api = DirectorApi(
      ApiClient(SessionStore(), onUnauthorized: widget.auth.sessionExpired).dio,
    );
    _future = _api.listCenters();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    final next = _api.listCenters(search: _search.text.trim());
    setState(() => _future = next);
    await next;
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Centers',
      showBack: true,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.screenPadding,
              AppSpacing.sm,
              AppSpacing.screenPadding,
              AppSpacing.sm,
            ),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _reload(),
              decoration: InputDecoration(
                hintText: 'Search center name',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: IconButton(
                  onPressed: _reload,
                  icon: const Icon(Icons.arrow_forward_rounded),
                ),
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _reload,
              child: FutureBuilder<List<Map<String, dynamic>>>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const PgLoadingState();
                  }
                  if (snapshot.hasError) {
                    return PgErrorState(
                      message: '${snapshot.error}',
                      onRetry: _reload,
                    );
                  }
                  final items = snapshot.data ?? const <Map<String, dynamic>>[];
                  if (items.isEmpty) {
                    return ListView(
                      children: const [
                        SizedBox(height: 80),
                        PgEmptyState(
                          icon: Icon(Icons.apartment_outlined),
                          message: 'No active centers found.',
                        ),
                      ],
                    );
                  }
                  return ListView.separated(
                    padding: const EdgeInsets.fromLTRB(
                      AppSpacing.screenPadding,
                      AppSpacing.sm,
                      AppSpacing.screenPadding,
                      AppSpacing.xl,
                    ),
                    itemCount: items.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      final item = items[index];
                      return _CenterListCard(
                        item: item,
                        onTap: () => context.push(
                          '/director/centers/${item['id']}',
                          extra: item,
                        ),
                      );
                    },
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CenterListCard extends StatelessWidget {
  const _CenterListCard({required this.item, required this.onTap});

  final Map<String, dynamic> item;
  final VoidCallback onTap;

  String _text(String key, {String fallback = '—'}) {
    final value = '${item[key] ?? ''}'.trim();
    return value.isEmpty ? fallback : value;
  }

  String _count(String key) => '${item[key] ?? 0}';

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Ink(
          decoration: BoxDecoration(
            color: const Color(0xFFE0F2FE),
            borderRadius: BorderRadius.circular(22),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0369A1).withValues(alpha: 0.08),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 12, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: const Color(0xFF0369A1),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Icon(
                        Icons.apartment_rounded,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _text('name', fallback: 'Center'),
                            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
                          Text(
                            _text('scheme_name', fallback: _text('project_name')),
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: AppColors.textSecondary,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  'Center Manager: ${_text('center_manager_name', fallback: 'Not assigned')}',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    _StatChip(
                      label: 'Employees',
                      value: _count('employees'),
                      background: const Color(0xFFE8F1FF),
                    ),
                    const SizedBox(width: 8),
                    _StatChip(
                      label: 'Punched In',
                      value: _count('punched_in_today'),
                      background: const Color(0xFFE6F7F1),
                    ),
                    const SizedBox(width: 8),
                    _StatChip(
                      label: 'Active Routes',
                      value: _count('active_routes'),
                      background: const Color(0xFFF0E9FF),
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

class _StatChip extends StatelessWidget {
  const _StatChip({
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
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}
