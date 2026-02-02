<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('bressol_get_home_data')) {
    function bressol_get_home_data(): array
    {
        return [
            'hero_slides' => [
                [
                    'title' => 'Valenciaanse smaken, rustig gekozen.',
                    'copy' => 'Bressol selecteert delicatessen met ambachtelijke herkomst en een sobere, mediterrane stijl.',
                    'image_id' => 0,
                    'image_ratio' => '16 / 9',
                    'primary_cta' => [
                        'label' => 'Ontdek de selectie',
                        'url' => '#',
                    ],
                    'secondary_cta' => [
                        'label' => 'Start met advies',
                        'url' => '#',
                    ],
                ],
                [
                    'title' => 'Voor tafel, borrel en cadeau.',
                    'copy' => 'Samenstellingen met balans, klaar om te schenken of te delen.',
                    'image_id' => 0,
                    'image_ratio' => '16 / 9',
                    'primary_cta' => [
                        'label' => 'Bekijk pakketten',
                        'url' => '#',
                    ],
                    'secondary_cta' => [
                        'label' => 'Zo werkt het',
                        'url' => '#',
                    ],
                ],
                [
                    'title' => 'Herkomst met karakter.',
                    'copy' => 'Kleine makers uit Valencia, gekozen op continuiteit en vakmanschap.',
                    'image_id' => 0,
                    'image_ratio' => '16 / 9',
                    'primary_cta' => [
                        'label' => 'Lees meer',
                        'url' => '#',
                    ],
                    'secondary_cta' => [
                        'label' => 'Onze momenten',
                        'url' => '#',
                    ],
                ],
            ],
            'trust_items' => [
                'Herkomst met karakter',
                'Ambachtelijk gekozen',
                'Evenwichtige smaak',
                'Rustige presentatie',
            ],
            'shortcuts' => [
                [
                    'title' => 'Advies op maat',
                    'copy' => 'Vertel je moment en ontvang richting.',
                    'url' => '#',
                ],
                [
                    'title' => 'Pakketten',
                    'copy' => 'Samengesteld met rust en balans.',
                    'url' => '#',
                ],
                [
                    'title' => 'Bestsellers',
                    'copy' => 'De meest gekozen selectie.',
                    'url' => '#',
                ],
            ],
            'bestsellers' => [
                [
                    'product_id' => '101',
                    'title' => 'Aperitief box',
                    'subtitle' => 'Rustige start',
                    'price' => '€34',
                    'image_id' => 0,
                    'image_ratio' => '4 / 5',
                ],
                [
                    'product_id' => '102',
                    'title' => 'Diner selectie',
                    'subtitle' => 'Voor aan tafel',
                    'price' => '€52',
                    'image_id' => 0,
                    'image_ratio' => '4 / 5',
                ],
                [
                    'product_id' => '103',
                    'title' => 'Cadeau duo',
                    'subtitle' => 'Elegant gegeven',
                    'price' => '€41',
                    'image_id' => 0,
                    'image_ratio' => '4 / 5',
                ],
                [
                    'product_id' => '104',
                    'title' => 'Weekend tafel',
                    'subtitle' => 'Zacht en mediterraan',
                    'price' => '€58',
                    'image_id' => 0,
                    'image_ratio' => '4 / 5',
                ],
            ],
            'moments' => [
                [
                    'title' => 'Aperitief',
                    'copy' => 'Kleine accenten voor het begin.',
                    'url' => '#',
                ],
                [
                    'title' => 'Diner',
                    'copy' => 'Rustige combinaties aan tafel.',
                    'url' => '#',
                ],
                [
                    'title' => 'Cadeau',
                    'copy' => 'Geef met soberheid en stijl.',
                    'url' => '#',
                ],
                [
                    'title' => 'Weekend',
                    'copy' => 'Lange momenten met karakter.',
                    'url' => '#',
                ],
                [
                    'title' => 'Feest',
                    'copy' => 'Warme, gedeelde selectie.',
                    'url' => '#',
                ],
                [
                    'title' => 'Zakelijk',
                    'copy' => 'Stijlvol zonder ruis.',
                    'url' => '#',
                ],
            ],
            'editorials' => [
                [
                    'title' => 'Het ritme van Valencia',
                    'copy' => 'Een rustige selectie uit kleine ateliers.',
                    'url' => '#',
                    'image_id' => 0,
                    'image_ratio' => '3 / 2',
                ],
                [
                    'title' => 'Sober geselecteerd',
                    'copy' => 'Hoe we kiezen met balans en stijl.',
                    'url' => '#',
                    'image_id' => 0,
                    'image_ratio' => '3 / 2',
                ],
                [
                    'title' => 'Aan tafel met Bressol',
                    'copy' => 'Momenten, tafels en accenten.',
                    'url' => '#',
                    'image_id' => 0,
                    'image_ratio' => '3 / 2',
                ],
            ],
            'gifts' => [
                [
                    'title' => 'Gifts',
                    'copy' => 'Voor een rustige, elegante gift.',
                    'url' => '#',
                    'image_id' => 0,
                    'image_ratio' => '1 / 1',
                ],
                [
                    'title' => 'Packs',
                    'copy' => 'Samengesteld met mediterrane stijl.',
                    'url' => '#',
                    'image_id' => 0,
                    'image_ratio' => '1 / 1',
                ],
            ],
            'origin_cards' => [
                [
                    'title' => 'Ambacht',
                    'copy' => 'Kleine makers, grote aandacht.',
                ],
                [
                    'title' => 'Herkomst',
                    'copy' => 'Valenciaanse oorsprong, puur en sober.',
                ],
                [
                    'title' => 'Kwaliteit',
                    'copy' => 'Evenwichtige smaak en presentatie.',
                ],
            ],
            'newsletter' => [
                'title' => 'Bressol club',
                'copy' => 'Ontvang rustige updates en nieuwe selecties.',
                'cta_label' => 'Inschrijven',
                'privacy' => 'Geen spam. Uitschrijven kan altijd.',
            ],
        ];
    }
}
