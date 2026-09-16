import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../auth/user_role.dart';
import 'route_permissions.dart';
import '../../modules/admissions/screens/admission_hub_screen.dart';
import '../../modules/admissions/screens/admission_list_screen.dart';
import '../../modules/admissions/screens/admission_wizard_screen.dart';
import '../../modules/admissions/screens/employee_targets_screen.dart';
import '../../modules/admissions/screens/supervisor_admission_detail_screen.dart';
import '../../modules/admissions/screens/supervisor_admission_list_screen.dart';
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
import '../../modules/leaves/screens/leave_detail_screen.dart';
import '../../modules/leaves/screens/leave_form_screen.dart';
import '../../modules/leaves/screens/leave_hub_screen.dart';
import '../../modules/leaves/screens/leave_list_screen.dart';
import '../../modules/leaves/screens/supervisor_leave_detail_screen.dart';
import '../../modules/leaves/screens/supervisor_leave_list_screen.dart';
import '../../modules/manager/screens/manager_admission_targets_screen.dart';
import '../../modules/manager/screens/manager_create_admission_target_screen.dart';
import '../../modules/manager/screens/manager_create_employee_screen.dart';
import '../../modules/manager/screens/manager_employees_screen.dart';
import '../../modules/manager/screens/manager_reports_screen.dart';
import '../../modules/manager/screens/manager_route_tracking_screen.dart';
import '../../modules/manager/screens/manager_team_attendance_screen.dart';
import '../../modules/profile/screens/profile_screen.dart';
import '../../modules/updates/screens/force_update_screen.dart';
import '../updates/app_update_controller.dart';

GoRouter createRouter(
  AuthController auth,
  AppUpdateController updates, {
  GlobalKey<NavigatorState>? navigatorKey,
}) =>
    GoRouter(
      navigatorKey: navigatorKey,
      initialLocation: '/dashboard',
      refreshListenable: Listenable.merge([auth, updates]),
      redirect: (_, state) {
        final location = state.matchedLocation;
        if (updates.shouldShowUpdate) {
          return location == '/update-required' ? null : '/update-required';
        }
        if (auth.initializing || updates.checking) {
          return location == '/splash' ? null : '/splash';
        }
        if (!auth.authenticated) return location == '/login' ? null : '/login';
        if (auth.mustChangePassword) {
          return location == '/change-password' ? null : '/change-password';
        }
        if (location == '/login' ||
            location == '/splash' ||
            location == '/update-required') {
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
          path: '/update-required',
          builder: (_, _) => ForceUpdateScreen(updates: updates),
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
          path: '/admissions',
          builder: (_, _) => const AdmissionHubScreen(),
        ),
        GoRoute(
          path: '/admissions/new',
          builder: (_, _) => const AdmissionWizardScreen(),
        ),
        GoRoute(
          path: '/admissions/list',
          builder: (_, state) => AdmissionListScreen(
            initialStatus: state.uri.queryParameters['status'] ?? '',
          ),
        ),
        GoRoute(
          path: '/admissions/drafts',
          builder: (_, _) => const AdmissionListScreen(initialStatus: 'draft'),
        ),
        GoRoute(
          path: '/admissions/submitted',
          builder: (_, _) =>
              const AdmissionListScreen(initialStatus: 'submitted'),
        ),
        GoRoute(
          path: '/admissions/targets',
          builder: (_, _) => const EmployeeTargetsScreen(),
        ),
        GoRoute(
          path: '/admissions/:id',
          builder: (_, state) => AdmissionWizardScreen(
            admissionId: int.parse(state.pathParameters['id']!),
          ),
        ),
        GoRoute(
          path: '/leaves',
          builder: (_, _) => const LeaveHubScreen(),
        ),
        GoRoute(
          path: '/leaves/apply',
          builder: (_, _) => const LeaveFormScreen(),
        ),
        GoRoute(
          path: '/leaves/mine',
          builder: (_, _) => const LeaveListScreen(),
        ),
        GoRoute(
          path: '/leaves/:id/edit',
          builder: (_, state) => LeaveFormScreen(
            leaveId: int.parse(state.pathParameters['id']!),
          ),
        ),
        GoRoute(
          path: '/leaves/:id',
          builder: (_, state) => LeaveDetailScreen(
            leaveId: int.parse(state.pathParameters['id']!),
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
          path: '/manager/employees/create',
          builder: (_, _) => ManagerCreateEmployeeScreen(auth: auth),
        ),
        GoRoute(
          path: '/manager/employees',
          builder: (_, _) => ManagerEmployeesScreen(auth: auth),
        ),
        GoRoute(
          path: '/manager/admissions',
          builder: (_, state) => SupervisorAdmissionListScreen(
            auth: auth,
            apiPrefix: 'manager',
            initialStatus: state.uri.queryParameters['status'] ?? 'submitted',
          ),
        ),
        GoRoute(
          path: '/manager/admissions/:id',
          builder: (_, state) => SupervisorAdmissionDetailScreen(
            auth: auth,
            apiPrefix: 'manager',
            admissionId: int.parse(state.pathParameters['id']!),
          ),
        ),
        GoRoute(
          path: '/manager/admission-targets/create',
          builder: (_, _) => ManagerCreateAdmissionTargetScreen(auth: auth),
        ),
        GoRoute(
          path: '/manager/admission-targets',
          builder: (_, _) => ManagerAdmissionTargetsScreen(auth: auth),
        ),
        GoRoute(
          path: '/manager/reports',
          builder: (_, _) => ManagerReportsScreen(auth: auth),
        ),
        GoRoute(
          path: '/manager/leaves',
          builder: (_, _) => SupervisorLeaveListScreen(
            auth: auth,
            apiPrefix: 'manager',
          ),
        ),
        GoRoute(
          path: '/manager/leaves/:id',
          builder: (_, state) => SupervisorLeaveDetailScreen(
            auth: auth,
            apiPrefix: 'manager',
            leaveId: int.parse(state.pathParameters['id']!),
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
        GoRoute(
          path: '/director/admissions',
          builder: (_, state) => SupervisorAdmissionListScreen(
            auth: auth,
            apiPrefix: 'director',
            initialStatus: state.uri.queryParameters['status'] ?? 'submitted',
          ),
        ),
        GoRoute(
          path: '/director/admissions/:id',
          builder: (_, state) => SupervisorAdmissionDetailScreen(
            auth: auth,
            apiPrefix: 'director',
            admissionId: int.parse(state.pathParameters['id']!),
          ),
        ),
        GoRoute(
          path: '/director/leaves',
          builder: (_, _) => SupervisorLeaveListScreen(
            auth: auth,
            apiPrefix: 'director',
          ),
        ),
        GoRoute(
          path: '/director/leaves/:id',
          builder: (_, state) => SupervisorLeaveDetailScreen(
            auth: auth,
            apiPrefix: 'director',
            leaveId: int.parse(state.pathParameters['id']!),
          ),
        ),
      ],
    );
