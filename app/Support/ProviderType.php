<?php

namespace App\Support;

class ProviderType
{
    /**
     * Maps the trailing token of a GetStocks `provType` (e.g. "shutterstock_video")
     * to a stable internal kind + human-friendly Portuguese label.
     *
     * Unknown kinds fall through with the raw token humanized.
     */
    private const KIND_MAP = [
        'photo' => 'Foto',
        'photos' => 'Foto',
        'image' => 'Imagem',
        'images' => 'Imagem',
        'vector' => 'Vetor',
        'vectors' => 'Vetor',
        'illustration' => 'Ilustração',
        'illustrations' => 'Ilustração',
        'video' => 'Vídeo',
        'videos' => 'Vídeo',
        'footage' => 'Footage',
        'motion' => 'Motion',
        'editorial' => 'Editorial',
        'music' => 'Música',
        'audio' => 'Áudio',
        'sound' => 'Som',
        'sfx' => 'SFX',
        'template' => 'Template',
        'templates' => 'Template',
        'preset' => 'Preset',
        'icon' => 'Ícone',
        'icons' => 'Ícone',
        'font' => 'Fonte',
        'fonts' => 'Fonte',
        '3d' => '3D',
        'psd' => 'PSD',
        'ai' => 'AI',
        'eps' => 'EPS',
        'brush' => 'Brush',
        'addon' => 'Add-on',
        'plugin' => 'Plugin',
        'standard' => 'Padrão',
        'premium' => 'Premium',
        'enterprise' => 'Enterprise',
    ];

    public static function describe(?string $type): array
    {
        if (! $type) {
            return ['kind' => 'unknown', 'label' => 'Outro'];
        }

        // Strip the leading provider slug ("shutterstock_") to keep just the suffix.
        $parts = explode('_', $type);
        $kind = strtolower((string) array_pop($parts));

        $label = self::KIND_MAP[$kind] ?? ucfirst($kind);

        return ['kind' => $kind, 'label' => $label];
    }
}
