import 'package:flutter/material.dart';
import '../../../core/auth/user_role.dart';
import '../../auth/providers/auth_controller.dart';
import 'employee_dashboard_screen.dart';
import 'supervisor_dashboard_screen.dart';
import '../../director/screens/director_dashboard_screen.dart';

class RoleDashboardScreen extends StatelessWidget {
  const RoleDashboardScreen({super.key, required this.auth});
  final AuthController auth;

  @override
  Widget build(BuildContext context) {
    return switch (auth.userRole) {
      UserRole.employee => EmployeeDashboardScreen(auth: auth),
      UserRole.centerManager ||
      UserRole.projectHead =>
        SupervisorDashboardScreen(auth: auth),
      UserRole.director ||
      UserRole.admin =>
        DirectorDashboardScreen(auth: auth),
    };
  }
}
