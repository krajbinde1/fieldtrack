enum UserRole {
  employee('employee'),
  centerManager('center_manager'),
  projectHead('project_head'),
  director('director');

  const UserRole(this.value);
  final String value;

  static UserRole fromValue(String? value) {
    return UserRole.values.firstWhere(
      (role) => role.value == value,
      orElse: () => UserRole.employee,
    );
  }

  String get label => switch (this) {
        UserRole.employee => 'Employee',
        UserRole.centerManager => 'Center Manager',
        UserRole.projectHead => 'Project Head',
        UserRole.director => 'Director',
      };

  bool get isEmployee => this == UserRole.employee;
  bool get isCenterManager => this == UserRole.centerManager;
  bool get isProjectHead => this == UserRole.projectHead;
  bool get isDirector => this == UserRole.director;
  bool get isManager => isProjectHead || isCenterManager;

  bool canAccessEmployeeWorkflow() => isEmployee;
  bool canAccessManagerRoutes() => isProjectHead || isCenterManager;
  bool canAccessDirectorRoutes() => isDirector;
}
