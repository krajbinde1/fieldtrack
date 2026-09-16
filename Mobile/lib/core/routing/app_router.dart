import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../auth/user_role.dart';
import 'route_permissions.dart';
import '../../modules/attendance/models/attendance.dart';
import '../../modules/attendance/screens/attendance_detail.dart';
import '../../modules/attendance/screens/attendance_history.dart';
import '../../modules/attendance/screens/attendance_home.dart';
import '../../modules/attendance/screens/punch_in_screen.dart';
import '../../modules/attendance/screens/punch_out_screen.dart';
import '../../modules/auth/providers/auth_controller.dart';
import '../../modules/auth/screens/change_password_screen.dart';
import '../../modules/auth/screens/login_screen.dart';
import '../../modules/auth/screens/splash_screen.dart';
import '../../modules/dashboard/screens/role_dashboard_screen.dart';
import '../../modules/director/screens/director_route_tracking_screen.dart';
import '../../modules/director/screens/director_team_attendance_screen.dart';
import '../../modules/manager/screens/manager_route_tracking_screen.dart';
import '../../modules/manager/screens/manager_team_attendance_screen.dart';
import '../../modules/profile/screens/profile_screen.dart';

GoRouter createRouter(
  AuthController auth, {
  GlobalKey<NavigatorState>? navigatorKey,
}) =>
    GoRouter(
      navigatorKey: navigatorKey,
      initialLocation: '/dashboard',
      refreshListenable: auth,
      redirect: (_, state) {
        final location = state.matchedLocation;
        if (auth.initializing) {
          return location == '/splash' ? null : '/splash';
        }
        if (!auth.authenticated) return location == '/login' ? null : '/login';
        if (auth.mustChangePassword) {
          return location == '/change-password' ? null : '/change-password';
        }
        if (location == '/login' || location == '/splash') {
          return '/dashboard';
        }

        final role = UserRole.fromValue(auth.session?.user.role);
        if (!RoutePermissions.canAccessPath(location, role)) {
          return '/dashboard';
        }
        return null;
      },
      routes: [
        GoRoute(
          path: '/splash',
          builder: (_, _) => SplashScreen(auth: auth),
        ),
        GoRoute(
          path: '/login',
          builder: (_, _) => LoginScreen(auth: auth),
        ),
        GoRoute(
          path: '/change-password',
          builder: (_, _) => ChangePasswordScreen(auth: auth),
        ),
        GoRoute(
          path: '/dashboard',
          builder: (_, _) => RoleDashboardScreen(auth: auth),
        ),
        GoRoute(
          path: '/profile',
          builder: (_, _) => ProfileScreen(auth: auth),
        ),
        GoRoute(
          path: '/attendance',
          builder: (_, _) => const AttendanceHome(),
        ),
        GoRoute(
          path: '/attendance/punch-in',
          builder: (_, _) => const PunchInScreen(),
        ),
        GoRoute(
          path: '/attendance/punch-out',
          builder: (_, _) => const PunchOutScreen(),
        ),
        GoRoute(
          path: '/attendance/history',
          builder: (_, _) => const AttendanceHistory(),
        ),
        GoRoute(
          path: '/attendance/detail',
          builder: (_, state) => AttendanceDetail(
            attendance: state.extra as Attendance,
          ),
        ),
        GoRoute(
          path: '/manager/team-attendance',
          builder: (_, _) => ManagerTeamAttendanceScreen(auth: auth),
        ),
        GoRoute(
          path: '/manager/team-attendance/:id',
          builder: (_, state) => ManagerTeamAttendanceDetailScreen(
            auth: auth,
            attendanceId: int.parse(state.pathParameters['id']!),
          ),
        ),
        GoRoute(
          path: '/manager/route-tracking',
          builder: (_, _) => ManagerRouteTrackingScreen(auth: auth),
        ),
        GoRoute(
          path: '/manager/route-tracking/:id',
          builder: (_, state) => ManagerRouteMapScreen(
            auth: auth,
            attendanceId: int.parse(state.pathParameters['id']!),
          ),
        ),
        GoRoute(
          path: '/director/team-attendance',
          builder: (_, _) => DirectorTeamAttendanceScreen(auth: auth),
        ),
        GoRoute(
          path: '/director/route-tracking',
          builder: (_, _) => DirectorRouteTrackingScreen(auth: auth),
        ),
        GoRoute(
          path: '/director/route-tracking/:id',
          builder: (_, state) => DirectorRouteMapScreen(
            auth: auth,
            attendanceId: int.parse(state.pathParameters['id']!),
          ),
        ),
      ],
    );
