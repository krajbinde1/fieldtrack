import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_errors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/manager_api.dart';

class ManagerCreateEmployeeScreen extends StatefulWidget {
  const ManagerCreateEmployeeScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  State<ManagerCreateEmployeeScreen> createState() =>
      _ManagerCreateEmployeeScreenState();
}

class _ManagerCreateEmployeeScreenState
    extends State<ManagerCreateEmployeeScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _mobile = TextEditingController();
  final _email = TextEditingController();
  final _loginId = TextEditingController();
  final _password = TextEditingController();

  late final ManagerApi _api;
  bool _loading = true;
  bool _saving = false;
  String? _error;
  int? _centerId;
  String _staffRole = 'mobilizer';
  List<Map<String, dynamic>> _centers = const [];
  Map<String, String> _roles = const {
    'mis': 'MIS',
    'mobilizer': 'Mobilizer',
    'housekeeper': 'Housekeeper',
    'trainer': 'Trainer',
  };

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
    _name.dispose();
    _mobile.dispose();
    _email.dispose();
    _loginId.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    try {
      final meta = await _api.employeesMeta();
      final centers = (meta['centers'] as List?)
              ?.whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList() ??
          const [];
      final rolesRaw = meta['staff_roles'];
      final roles = <String, String>{};
      if (rolesRaw is Map) {
        for (final entry in rolesRaw.entries) {
          roles['${entry.key}'] = '${entry.value}';
        }
      }
      if (!mounted) return;
      setState(() {
        _centers = centers;
        if (roles.isNotEmpty) _roles = roles;
        _centerId = centers.length == 1
            ? int.tryParse('${centers.first['id']}')
            : _centerId;
        _staffRole = _roles.containsKey(_staffRole)
            ? _staffRole
            : (_roles.keys.first);
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

  Future<void> _save() async {
    if (_saving || !(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await _api.createEmployee(
        fullName: _name.text.trim(),
        mobile: _mobile.text.trim(),
        password: _password.text,
        staffRole: _staffRole,
        centerId: _centerId,
        email: _email.text.trim(),
        loginId: _loginId.text.trim(),
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
      title: 'Add User',
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
                        if (_centers.length > 1)
                          DropdownButtonFormField<int>(
                            initialValue: _centerId,
                            decoration: const InputDecoration(labelText: 'Center'),
                            items: [
                              for (final center in _centers)
                                DropdownMenuItem(
                                  value: int.tryParse('${center['id']}'),
                                  child: Text('${center['name'] ?? 'Center'}'),
                                ),
                            ],
                            onChanged: (value) => setState(() => _centerId = value),
                            validator: (value) =>
                                value == null ? 'Select a center' : null,
                          )
                        else if (_centers.length == 1)
                          InputDecorator(
                            decoration: const InputDecoration(labelText: 'Center'),
                            child: Text('${_centers.first['name'] ?? 'Assigned center'}'),
                          ),
                        const SizedBox(height: AppSpacing.md),
                        TextFormField(
                          controller: _name,
                          textCapitalization: TextCapitalization.words,
                          decoration: const InputDecoration(labelText: 'Name'),
                          validator: (value) =>
                              (value == null || value.trim().isEmpty)
                                  ? 'Enter a name'
                                  : null,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        TextFormField(
                          controller: _mobile,
                          keyboardType: TextInputType.phone,
                          maxLength: 10,
                          decoration: const InputDecoration(
                            labelText: 'Mobile Number',
                            counterText: '',
                          ),
                          validator: (value) {
                            final text = value?.trim() ?? '';
                            if (!RegExp(r'^[6-9][0-9]{9}$').hasMatch(text)) {
                              return 'Enter a valid 10-digit mobile';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: AppSpacing.md),
                        TextFormField(
                          controller: _email,
                          keyboardType: TextInputType.emailAddress,
                          decoration: const InputDecoration(labelText: 'Email (optional)'),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        TextFormField(
                          controller: _loginId,
                          decoration: const InputDecoration(
                            labelText: 'Login ID (optional)',
                            helperText: 'Blank uses the mobile number.',
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        TextFormField(
                          controller: _password,
                          obscureText: true,
                          decoration: const InputDecoration(labelText: 'Password'),
                          validator: (value) =>
                              (value == null || value.length < 8)
                                  ? 'Minimum 8 characters'
                                  : null,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        DropdownButtonFormField<String>(
                          initialValue: _staffRole,
                          decoration: const InputDecoration(labelText: 'Login Role'),
                          items: [
                            for (final entry in _roles.entries)
                              DropdownMenuItem(
                                value: entry.key,
                                child: Text(entry.value),
                              ),
                          ],
                          onChanged: (value) {
                            if (value != null) setState(() => _staffRole = value);
                          },
                        ),
                      ],
                    ),
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: AppSpacing.sm),
                  Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ],
                const SizedBox(height: AppSpacing.md),
                FilledButton(
                  onPressed: _saving ? null : _save,
                  child: Text(_saving ? 'Saving…' : 'Create User'),
                ),
              ],
            ),
    );
  }
}
