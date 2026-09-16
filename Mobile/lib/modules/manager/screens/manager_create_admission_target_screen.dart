import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_errors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/manager_api.dart';

class ManagerCreateAdmissionTargetScreen extends StatefulWidget {
  const ManagerCreateAdmissionTargetScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  State<ManagerCreateAdmissionTargetScreen> createState() =>
      _ManagerCreateAdmissionTargetScreenState();
}

class _ManagerCreateAdmissionTargetScreenState
    extends State<ManagerCreateAdmissionTargetScreen> {
  final _formKey = GlobalKey<FormState>();
  final _count = TextEditingController();

  late final ManagerApi _api;
  bool _loading = true;
  bool _saving = false;
  String? _error;
  int? _employeeId;
  String _targetType = 'weekly';
  DateTime _period = DateTime.now();
  List<Map<String, dynamic>> _employees = const [];

  @override
  void initState() {
    super.initState();
    _api = ManagerApi(
      ApiClient(SessionStore(), onUnauthorized: widget.auth.sessionExpired).dio,
    );
    _bootstrap();
  }

  @override
  void dispose() {
    _count.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    try {
      final employees = await _api.listEmployees();
      if (!mounted) return;
      setState(() {
        _employees = employees.where((row) => row['status'] == true).toList();
        _employeeId = _employees.isEmpty
            ? null
            : int.tryParse('${_employees.first['id']}');
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = errorMessage(error);
        _loading = false;
      });
    }
  }

  Future<void> _pickPeriod() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _period,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 366)),
    );
    if (picked == null) return;
    setState(() => _period = picked);
  }

  Future<void> _save() async {
    if (_saving || !(_formKey.currentState?.validate() ?? false)) return;
    if (_employeeId == null) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await _api.createAdmissionTarget(
        employeeId: _employeeId!,
        targetType: _targetType,
        targetCount: int.parse(_count.text.trim()),
        period: DateFormat('yyyy-MM-dd').format(_period),
      );
      if (!mounted) return;
      context.pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = errorMessage(error);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Set Admission Target',
      showBack: true,
      body: _loading
          ? const PgLoadingState()
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                PgCard(
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        DropdownButtonFormField<int>(
                          initialValue: _employeeId,
                          decoration: const InputDecoration(labelText: 'Employee'),
                          items: [
                            for (final employee in _employees)
                              DropdownMenuItem(
                                value: int.tryParse('${employee['id']}'),
                                child: Text(
                                  [
                                    employee['full_name'] ?? 'Employee',
                                    employee['staff_role_label'] ??
                                        employee['staff_role'],
                                  ].where((part) => '$part'.trim().isNotEmpty).join(' · '),
                                ),
                              ),
                          ],
                          onChanged: (value) => setState(() => _employeeId = value),
                          validator: (value) =>
                              value == null ? 'Select an employee' : null,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        DropdownButtonFormField<String>(
                          initialValue: _targetType,
                          decoration: const InputDecoration(labelText: 'Target type'),
                          items: const [
                            DropdownMenuItem(value: 'weekly', child: Text('Weekly')),
                            DropdownMenuItem(value: 'monthly', child: Text('Monthly')),
                          ],
                          onChanged: (value) {
                            if (value != null) setState(() => _targetType = value);
                          },
                        ),
                        const SizedBox(height: AppSpacing.md),
                        TextFormField(
                          controller: _count,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(labelText: 'Target count'),
                          validator: (value) {
                            final parsed = int.tryParse(value?.trim() ?? '');
                            if (parsed == null || parsed < 0) {
                              return 'Enter a valid target';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: AppSpacing.md),
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Target period'),
                          subtitle: Text(
                            _targetType == 'monthly'
                                ? 'Any date in the target month'
                                : 'Any date in the target week',
                          ),
                          trailing: TextButton(
                            onPressed: _pickPeriod,
                            child: Text(DateFormat('d MMM yyyy').format(_period)),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                if (_employees.isEmpty) ...[
                  const SizedBox(height: AppSpacing.sm),
                  const Text('No employees in your assigned center(s).'),
                ],
                if (_error != null) ...[
                  const SizedBox(height: AppSpacing.sm),
                  Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ],
                const SizedBox(height: AppSpacing.md),
                FilledButton(
                  onPressed: _saving || _employees.isEmpty ? null : _save,
                  child: Text(_saving ? 'Saving…' : 'Save Target'),
                ),
              ],
            ),
    );
  }
}
