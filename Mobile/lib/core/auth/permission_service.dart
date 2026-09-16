import '../auth/user_role.dart';

class PermissionService {
  const PermissionService(this._permissions, this.role);

  final List<String> _permissions;
  final UserRole role;

  bool has(String permission) => _permissions.contains(permission);

  bool get canAccessEmployeeWorkflow =>
      has('attendance') || role.canAccessEmployeeWorkflow();

  bool get canViewManagerDashboard =>
      has('project_head_dashboard') ||
      has('center_manager_dashboard') ||
      role.isProjectHead ||
      role.isCenterManager;

  bool get canViewDirectorDashboard =>
      has('director_dashboard') || role.isDirector;
}
