import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import '../design/app_colors.dart';

/// Subtle app version label from package metadata (`pubspec.yaml` version).
class AppVersionLabel extends StatelessWidget {
  const AppVersionLabel({
    super.key,
    this.color,
    this.textAlign = TextAlign.center,
  });

  final Color? color;
  final TextAlign textAlign;

  static final Future<PackageInfo> _info = PackageInfo.fromPlatform();

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<PackageInfo>(
      future: _info,
      builder: (context, snapshot) {
        final info = snapshot.data;
        if (info == null) return const SizedBox.shrink();
        final version = info.version.trim();
        final build = info.buildNumber.trim();
        if (version.isEmpty && build.isEmpty) return const SizedBox.shrink();
        final style = Theme.of(context).textTheme.bodySmall?.copyWith(
              color: color ?? AppColors.textMuted,
              fontSize: 11,
              fontWeight: FontWeight.w500,
              height: 1.2,
            );
        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (version.isNotEmpty)
              Text(
                'Version $version',
                textAlign: textAlign,
                style: style,
              ),
            if (build.isNotEmpty)
              Text(
                'Build $build',
                textAlign: textAlign,
                style: style,
              ),
          ],
        );
      },
    );
  }
}
