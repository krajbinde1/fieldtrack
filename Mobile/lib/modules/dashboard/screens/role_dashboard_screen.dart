import 'package:flutter/material.dart';
import '../../../core/auth/user_role.dart';
import '../../auth/providers/auth_controller.dart';
import 'employee_dashboard_screen.dart';
import 'supervisor_dashboard_screen.dart';
import '../../director/screens/director_dashboard_screen.dart';

class _NoRoleAccess extends StatelessWidget {
  const _NoRoleAccess();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Text(
            'This account does not have access. Ask an administrator to assign a current role.',
            textAlign: TextAlign.center,
          ),
        ),
      ),
    );
  }
}

class RoleDashboardScreen extends StatelessWidget {
  const RoleDashboardScreen({super.key, required this.auth});
  final AuthController auth;

  @override
  Widget build(BuildContext context) {
    return switch (auth.userRole) {
      UserRole.employee => EmployeeDashboardScreen(auth: auth),
      UserRole.centerManager => SupervisorDashboardScreen(auth: auth),
      UserRole.projectHead => const _NoRoleAccess(),
      UserRole.centerCoordinator ||
      UserRole.director ||
      UserRole.admin =>
        DirectorDashboardScreen(auth: auth),
    };
  }
}
