import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_errors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../../../core/widgets/prompt_dialog.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/manager_api.dart';

class ManagerEmployeesScreen extends StatefulWidget {
  const ManagerEmployeesScreen({
    super.key,
    required this.auth,
    this.centerId,
    this.apiPrefix = 'manager',
  });

  final AuthController auth;
  final int? centerId;
  final String apiPrefix;

  @override
  State<ManagerEmployeesScreen> createState() => _ManagerEmployeesScreenState();
}

class _ManagerEmployeesScreenState extends State<ManagerEmployeesScreen> {
  late final ManagerApi _api;
  late Future<List<Map<String, dynamic>>> _future;
  int? _resettingId;

  @override
  void initState() {
    super.initState();
    _api = ManagerApi(
      ApiClient(SessionStore(), onUnauthorized: widget.auth.sessionExpired).dio,
      prefix: widget.apiPrefix,
    );
    _future = _api.listEmployees(centerId: widget.centerId);
  }

  Future<void> _refresh() async {
    setState(() => _future = _api.listEmployees(centerId: widget.centerId));
    await _future;
  }

  Future<void> _resetPassword(Map<String, dynamic> row) async {
    final id = int.tryParse('${row['id'] ?? ''}');
    if (id == null || _resettingId != null) return;

    final name = '${row['full_name'] ?? 'this user'}';
    final confirmed = await confirmAction(
      context,
      title: 'Reset Password',
      message:
          'Reset the password for $name to the last 4 digits of the current mobile number?',
    );
    if (!confirmed || !mounted) return;

    setState(() => _resettingId = id);
    try {
      final result = await _api.resetEmployeePassword(id);
      if (!mounted) return;
      final data = result['data'];
      final defaultPassword = data is Map ? '${data['default_password'] ?? ''}' : '';
      final message = '${result['message'] ?? ''}'.trim().isNotEmpty
          ? '${result['message']}'
          : 'Password reset successfully. Default password: $defaultPassword';
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Password reset'),
          content: Text(message),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('OK'),
            ),
          ],
        ),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(errorMessage(error))),
      );
    } finally {
      if (mounted) setState(() => _resettingId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Users / Employees',
      showBack: true,
      floatingActionButton: widget.auth.userRole.isCenterManager
          ? FloatingActionButton.extended(
              onPressed: () async {
                final result = await context.push<bool>('/manager/employees/create');
                if (result == true) await _refresh();
              },
              icon: const Icon(Icons.person_add_alt_1_rounded),
              label: const Text('Add User'),
            )
          : null,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<Map<String, dynamic>>>(
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
            final items = snapshot.data ?? const <Map<String, dynamic>>[];
            if (items.isEmpty) {
              return ListView(
                children: const [
                  SizedBox(height: 80),
                  PgEmptyState(
                    icon: Icon(Icons.groups_outlined),
                    message: 'No employees in your assigned center(s).',
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
              itemBuilder: (context, index) {
                final row = items[index];
                final active = row['status'] == true;
                return PgCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              '${row['full_name'] ?? 'Employee'}',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          PgStatusBadge(
                            label: active ? 'Active' : 'Inactive',
                            tone: active
                                ? PgStatusTone.approved
                                : PgStatusTone.neutral,
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        [
                          row['employee_code'],
                          row['staff_role_label'],
                          row['center_name'],
                        ].where((part) => '$part'.trim().isNotEmpty).join(' · '),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                      if ('${row['login_id'] ?? ''}'.trim().isNotEmpty)
                        Text(
                          'Login ID: ${row['login_id']}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      if (widget.auth.userRole.isCenterManager) ...[
                        const SizedBox(height: AppSpacing.sm),
                        Align(
                          alignment: Alignment.centerRight,
                          child: _resettingId ==
                                  int.tryParse('${row['id'] ?? ''}')
                              ? const SizedBox(
                                  width: 24,
                                  height: 24,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : TextButton.icon(
                                  onPressed: () => _resetPassword(row),
                                  icon: const Icon(Icons.lock_reset_rounded),
                                  label: const Text('Reset Password'),
                                ),
                        ),
                      ],
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
}
