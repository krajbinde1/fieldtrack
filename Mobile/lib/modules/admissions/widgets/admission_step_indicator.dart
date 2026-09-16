import 'package:flutter/material.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';

class AdmissionStepIndicator extends StatelessWidget {
  const AdmissionStepIndicator({
    super.key,
    required this.current,
    required this.labels,
  });

  final int current;
  final List<String> labels;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          children: [
            for (var i = 0; i < labels.length; i++) ...[
              _Dot(index: i, current: current),
              if (i < labels.length - 1)
                Expanded(
                  child: Container(
                    height: 2,
                    color: i < current ? AppColors.primary : AppColors.border,
                  ),
                ),
            ],
          ],
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'Step ${current + 1} of ${labels.length} · ${labels[current]}',
          style: Theme.of(context).textTheme.labelLarge?.copyWith(
            color: AppColors.textSecondary,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _Dot extends StatelessWidget {
  const _Dot({required this.index, required this.current});

  final int index;
  final int current;

  @override
  Widget build(BuildContext context) {
    final active = index <= current;
    return Container(
      width: 28,
      height: 28,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: active ? AppColors.primary : Colors.white,
        border: Border.all(
          color: active ? AppColors.primary : AppColors.border,
          width: 2,
        ),
        shape: BoxShape.circle,
      ),
      child: Text(
        '${index + 1}',
        style: TextStyle(
          color: active ? Colors.white : AppColors.textMuted,
          fontWeight: FontWeight.w800,
          fontSize: 12,
        ),
      ),
    );
  }
}
