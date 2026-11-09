<?php
class GetLeadStatusStyle
{
    public static function get($statusId)
    {
        $statusId = (int)$statusId;

        $map = [
            1 => [
                'tag' => 'New',
                'label_bg' => '#E8F0FE',
                'label_text' => '#3367D6',
                'ribbon_bg' => '#4C8BF5',
                'ribbon_text' => 'New',
                'icon' => 'mdi:sparkles',
                'animation_class' => 'lead-icon-pulse'
            ],
            2 => [
                'tag' => 'Hot',
                'label_bg' => '#FFE1E1',
                'label_text' => '#C0392B',
                'ribbon_bg' => '#EB5757',
                'ribbon_text' => 'Hot',
                'icon' => 'mdi:fire',
                'animation_class' => 'lead-icon-flame'
            ],
            3 => [
                'tag' => 'Warm',
                'label_bg' => '#FFF4CC',
                'label_text' => '#B78103',
                'ribbon_bg' => '#F4C542',
                'ribbon_text' => 'Warm',
                'icon' => 'mdi:white-balance-sunny',
                'animation_class' => 'lead-icon-pulse'
            ],
            4 => [
                'tag' => 'Busy',
                'label_bg' => '#FFE7D6',
                'label_text' => '#C96A28',
                'ribbon_bg' => '#F2994A',
                'ribbon_text' => 'Busy',
                'icon' => 'mdi:phone-in-talk',
                'animation_class' => 'lead-icon-shake'
            ],
            5 => [
                'tag' => 'Sleep',
                'label_bg' => '#E7F0FF',
                'label_text' => '#3E6FB6',
                'ribbon_bg' => '#4A90E2',
                'ribbon_text' => 'Sleep',
                'icon' => 'mdi:sleep',
                'animation_class' => 'lead-icon-float'
            ],
            6 => [
                'tag' => 'Enrolled',
                'label_bg' => '#fdd7fd',
                'label_text' => '#e807c6',
                'ribbon_bg' => '#e807c6',
                'ribbon_text' => 'Enrolled',
                'icon' => 'mdi:account-check',
                'animation_class' => 'lead-icon-bounce'
            ],
            7 => [
                'tag' => 'Future',
                'label_bg' => '#E4F6E8',
                'label_text' => '#2E8B57',
                'ribbon_bg' => '#27AE60',
                'ribbon_text' => 'Future',
                'icon' => 'mdi:calendar-clock',
                'animation_class' => 'lead-icon-pulse'
            ],
            8 => [
                'tag' => 'Cold',
                'label_bg' => '#E3E3E3',
                'label_text' => '#666666',
                'ribbon_bg' => '#9E9E9E',
                'ribbon_text' => 'Cold',
                'icon' => 'mdi:snowflake',
                'animation_class' => 'lead-icon-spin-slow'
            ],
        ];

        return $map[$statusId] ?? [
            'tag' => '',
            'label_bg' => '#F3F4F6',
            'label_text' => '#374151',
            'ribbon_bg' => '#9CA3AF',
            'ribbon_text' => '',
            'icon' => 'mdi:tag-outline',
            'animation_class' => 'lead-icon-pulse'
        ];
    }
}