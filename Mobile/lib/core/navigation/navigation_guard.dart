import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';
import 'package:go_router/go_router.dart';

const String kRoleHomePath = '/dashboard';

bool _navigationLocked = false;

bool _canNavigateNow() {
  final phase = SchedulerBinding.instance.schedulerPhase;
  return phase == SchedulerPhase.idle ||
      phase == SchedulerPhase.postFrameCallbacks;
}

void _unlockAfterFrame() {
  SchedulerBinding.instance.addPostFrameCallback((_) {
    _navigationLocked = false;
  });
}

void runGuardedNavigation(BuildContext context, void Function() action) {
  if (!context.mounted || _navigationLocked) return;
  _navigationLocked = true;

  void run() {
    try {
      if (!context.mounted) return;
      action();
    } finally {
      _unlockAfterFrame();
    }
  }

  if (_canNavigateNow()) {
    run();
    return;
  }

  SchedulerBinding.instance.addPostFrameCallback((_) => run());
}

void _popIfPossible(BuildContext context, [Object? result]) {
  if (!context.mounted) return;
  if (context.canPop()) {
    context.pop(result);
    return;
  }
  final navigator = Navigator.maybeOf(context);
  if (navigator != null && navigator.canPop()) {
    navigator.pop(result);
  }
}

void _goFallback(BuildContext context, String fallback) {
  if (!context.mounted) return;
  final current = GoRouterState.of(context).uri.path;
  if (current == fallback) return;
  context.go(fallback);
}

void safePop(BuildContext context, [Object? result]) {
  runGuardedNavigation(context, () => _popIfPossible(context, result));
}

void safeGo(BuildContext context, String location) {
  runGuardedNavigation(context, () {
    if (!context.mounted) return;
    context.go(location);
  });
}

void smartBack(
  BuildContext context, {
  String fallback = kRoleHomePath,
  Object? result,
}) {
  runGuardedNavigation(context, () {
    if (!context.mounted) return;
    if (context.canPop() || (Navigator.maybeOf(context)?.canPop() ?? false)) {
      _popIfPossible(context, result);
      return;
    }
    _goFallback(context, fallback);
  });
}

Future<void> afterNavigation(
  BuildContext context,
  Future<void> Function() action,
) async {
  if (!context.mounted) return;
  await SchedulerBinding.instance.endOfFrame;
  if (!context.mounted) return;
  await action();
}

class SafeBackScope extends StatelessWidget {
  const SafeBackScope({
    super.key,
    required this.child,
    this.onBack,
    this.fallback = kRoleHomePath,
  });

  final Widget child;
  final VoidCallback? onBack;
  final String fallback;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop) return;
        handleBack(context);
      },
      child: child,
    );
  }

  void handleBack(BuildContext context) {
    if (!context.mounted) return;
    if (onBack != null) {
      onBack!();
      return;
    }
    smartBack(context, fallback: fallback);
  }
}
