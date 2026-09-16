import '../auth/user_role.dart';

class RoutePermissions {
  const RoutePermissions._();

  static bool canAccessPath(String path, UserRole role) {
    if (path.startsWith('/profile') || path.startsWith('/change-password')) {
      return true;
    }

    if (path == '/dashboard' ||
        path == '/splash' ||
        path == '/login' ||
        path == '/update-required') {
      return true;
    }

    if (path.startsWith('/attendance')) {
      return role.canAccessOwnAttendance();
    }

    if (path.startsWith('/admissions') || path.startsWith('/leaves')) {
      return role.isEmployee;
    }

    if (path.startsWith('/manager')) {
      return role.isProjectHead || role.isCenterManager;
    }

    if (path.startsWith('/director')) {
      return role.isAdmin || role.isDirector;
    }

    return true;
  }
}
