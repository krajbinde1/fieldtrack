import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'core/api/api_config.dart';
import 'core/design/app_theme.dart';
import 'core/design/material_icon_retention.dart';
import 'core/routing/app_router.dart';
import 'core/updates/app_update_controller.dart';
import 'modules/attendance/route_tracking/route_tracking_config.dart';
import 'modules/attendance/route_tracking/route_tracking_lifecycle.dart';
import 'modules/attendance/route_tracking/route_tracking_log.dart';
import 'modules/attendance/route_tracking/route_tracking_service.dart';
import 'modules/auth/providers/auth_controller.dart';

final authController = AuthController();
final appUpdateController = AppUpdateController();
final rootNavigatorKey = GlobalKey<NavigatorState>();

final appRouter = createRouter(
  authController,
  appUpdateController,
  navigatorKey: rootNavigatorKey,
);

void main() {
  runZonedGuarded(() async {
    WidgetsFlutterBinding.ensureInitialized();
    // ignore: unnecessary_statements
    retainMaterialIconGlyphs();

    if (kDebugMode) {
      debugPrint('API base URL: ${ApiConfig.baseUrl}');
    }

    unawaited(_safeStartupHousekeeping());
    runApp(const ProviderScope(child: ParamFieldTrackApp()));
  }, (error, stack) {
    debugPrint('Uncaught startup/runtime error: $error\n$stack');
    routeTrackingLog('Uncaught error: $error');
  });
}

Future<void> _safeStartupHousekeeping() async {
  try {
    if (!routeTrackingRuntimeEnabled) {
      await RouteTrackingService.instance
          .disableRuntimeCleanup()
          .timeout(const Duration(seconds: 5));
    }
  } catch (error, stackTrace) {
    debugPrint('Startup housekeeping failed: $error\n$stackTrace');
  }
}

class ParamFieldTrackApp extends StatefulWidget {
  const ParamFieldTrackApp({super.key});

  @override
  State<ParamFieldTrackApp> createState() => _ParamFieldTrackAppState();
}

class _ParamFieldTrackAppState extends State<ParamFieldTrackApp>
    with WidgetsBindingObserver {
  bool _returnedFromBackground = false;
  bool _wasAuthenticated = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _wasAuthenticated = authController.authenticated;
    authController.addListener(_onAuthChanged);
  }

  @override
  void dispose() {
    authController.removeListener(_onAuthChanged);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  void _onAuthChanged() {
    final isAuth = authController.authenticated;
    if (isAuth && !_wasAuthenticated) {
      unawaited(appUpdateController.checkAfterLogin());
    }
    _wasAuthenticated = isAuth;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.hidden) {
      _returnedFromBackground = true;
      return;
    }
    if (state != AppLifecycleState.resumed || !_returnedFromBackground) {
      return;
    }
    _returnedFromBackground = false;
    unawaited(appUpdateController.checkOnForegroundResume());
  }

  @override
  Widget build(BuildContext context) => RouteTrackingLifecycle(
        child: MaterialApp.router(
          title: 'Param FieldTrack',
          debugShowCheckedModeBanner: false,
          themeMode: ThemeMode.light,
          routerConfig: appRouter,
          theme: AppTheme.light(),
          darkTheme: AppTheme.light(),
          builder: (context, child) => Stack(
            children: [
              const MaterialIconRetention(),
              ?child,
            ],
          ),
        ),
      );
}
