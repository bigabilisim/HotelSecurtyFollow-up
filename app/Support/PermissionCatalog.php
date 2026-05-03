<?php

declare(strict_types=1);

namespace App\Support;

final class PermissionCatalog
{
    public static function items(): array
    {
        return [
            'dashboard.view' => [
                'label' => 'Canlı Paneli Aç',
                'group' => 'Operasyon Akışı',
                'description' => 'Kullanıcı canlı güvenlik paneline girebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında kullanıcıyı düzenleyip Operasyon Akışı bölümünden "Canlı Paneli Aç" yetkisini işaretleyin.',
            ],
            'dashboard.block.door' => [
                'label' => 'Kapı İşlemi Bölümü',
                'group' => 'Canlı Panel Bölümleri',
                'description' => 'Canlı panelde hızlı giriş formunu ve kategori seçimlerini görür.',
                'grant_hint' => 'Yönetim > Kullanıcılar ekranında Panel Bölümleri bölümünden "Kapı İşlemi Bölümü" yetkisini açın. Giriş kaydı için ayrıca "Giriş Kaydı Oluştur" açık olmalıdır.',
            ],
            'dashboard.block.inside' => [
                'label' => 'İçeride Olanlar Bölümü',
                'group' => 'Canlı Panel Bölümleri',
                'description' => 'İçerideki kişileri, süreleri, durumları ve çıkış işlemlerini takip eder.',
                'grant_hint' => 'Yönetim > Kullanıcılar ekranında Panel Bölümleri bölümünden "İçeride Olanlar Bölümü" yetkisini açın.',
            ],
            'dashboard.block.activity' => [
                'label' => 'Canlı Akış Bölümü',
                'group' => 'Canlı Panel Bölümleri',
                'description' => 'Giriş, çıkış, süre aşımı, eskalasyon ve bildirim hareketlerini görür.',
                'grant_hint' => 'Yönetim > Kullanıcılar ekranında Panel Bölümleri bölümünden "Canlı Akış Bölümü" yetkisini açın.',
            ],
            'dashboard.block.stats' => [
                'label' => 'Özet Kartları',
                'group' => 'Canlı Panel Bölümleri',
                'description' => 'Bugünkü giriş, içerideki kişi, süre aşımı ve bildirim sayaçlarını görür.',
                'grant_hint' => 'Yönetim > Kullanıcılar ekranında Panel Bölümleri bölümünden "Özet Kartları" yetkisini açın.',
            ],
            'dashboard.block.verifications' => [
                'label' => 'Departman Onayı Bölümü',
                'group' => 'Canlı Panel Bölümleri',
                'description' => 'Bekleyen departman sorularını panelden görüp cevaplayabilir.',
                'grant_hint' => 'Yönetim > Kullanıcılar ekranında Panel Bölümleri bölümünden "Departman Onayı Bölümü" yetkisini açın. Cevap vermek için ayrıca "Departman / Süre Aşımı Onayı Cevapla" açık olmalıdır.',
            ],
            'dashboard.view_settings' => [
                'label' => 'Panel Görünüm Ayarı',
                'group' => 'Canlı Panel Bölümleri',
                'description' => 'Kullanıcı kendi ekranında görünüm, ölçek ve bölüm tercihlerini değiştirebilir.',
                'grant_hint' => 'Yönetim > Kullanıcılar ekranında Panel Bölümleri bölümünden "Panel Görünüm Ayarı" yetkisini açın.',
            ],
            'visits.create_entry' => [
                'label' => 'Giriş Kaydı Oluştur',
                'group' => 'Operasyon Akışı',
                'description' => 'Kapı işleminde yeni ziyaretçi girişi yapabilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Operasyon Akışı bölümünden "Giriş Kaydı Oluştur" yetkisini açın.',
            ],
            'visits.create_exit' => [
                'label' => 'Çıkış Kaydı Oluştur',
                'group' => 'Operasyon Akışı',
                'description' => 'İçerideki ziyaretçi için çıkış kaydı oluşturabilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Operasyon Akışı bölümünden "Çıkış Kaydı Oluştur" yetkisini açın.',
            ],
            'visits.view_all' => [
                'label' => 'İçerideki ve Geçmiş Kayıtları Gör',
                'group' => 'Operasyon Akışı',
                'description' => 'Canlı listedeki ve kayıt ekranlarındaki ziyaretçi bilgilerini görebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Operasyon Akışı bölümünden "İçerideki ve Geçmiş Kayıtları Gör" yetkisini açın.',
            ],
            'visits.department_verify' => [
                'label' => 'Departman / Süre Aşımı Onayı Cevapla',
                'group' => 'Operasyon Akışı',
                'description' => 'Süre aşımı sorusunda Evet/Hayır cevabı verebilir. Oğuz Bey gibi operasyon müdürlerinin onay vermesi için bu yetki açık olmalıdır.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında kullanıcıyı düzenleyin. Hazır Yetki Setleri bölümünden "Operasyon Müdürü" seçin veya Operasyon Akışı bölümündeki "Departman / Süre Aşımı Onayı Cevapla" kutusunu işaretleyin.',
            ],
            'categories.manage' => [
                'label' => 'Kategorileri Yönet',
                'group' => 'Tanımlar ve Yönetim',
                'description' => 'Kategori, hızlı seçim, süre ve uyarı tanımlarını düzenleyebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Tanımlar ve Yönetim bölümünden "Kategorileri Yönet" yetkisini açın.',
            ],
            'departments.manage' => [
                'label' => 'Departmanları ve Amirleri Yönet',
                'group' => 'Tanımlar ve Yönetim',
                'description' => 'Departman ve amir/e-posta bilgilerini düzenleyebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Tanımlar ve Yönetim bölümünden "Departmanları ve Amirleri Yönet" yetkisini açın.',
            ],
            'users.manage' => [
                'label' => 'Kullanıcı ve Yetki Yönet',
                'group' => 'Tanımlar ve Yönetim',
                'description' => 'Kullanıcı, rol ve görev bazlı yetki ayarlarını düzenleyebilir.',
                'grant_hint' => 'Bu yetki için mevcut bir sistem yöneticisinin Yönetim > Kullanıcı ve Yetki ekranından "Kullanıcı ve Yetki Yönet" kutusunu açması gerekir.',
            ],
            'visitors.manage' => [
                'label' => 'Kayıtlı Kişileri Yönet',
                'group' => 'Tanımlar ve Yönetim',
                'description' => 'Tekrar gelen kişi kartlarını görüntüleyip düzenleyebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Tanımlar ve Yönetim bölümünden "Kayıtlı Kişileri Yönet" yetkisini açın.',
            ],
            'watchlist.manage' => [
                'label' => 'Kara / Uyarı Listesini Yönet',
                'group' => 'Tanımlar ve Yönetim',
                'description' => 'Kara liste ve uyarı listesi kayıtlarını yönetebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Tanımlar ve Yönetim bölümünden "Kara / Uyarı Listesini Yönet" yetkisini açın.',
            ],
            'notifications.manage' => [
                'label' => 'Bildirim Genel Yönetimi',
                'group' => 'Bildirim, Rapor ve Sistem',
                'description' => 'Bildirim modülüne genel yönetim erişimi sağlar.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Bildirim, Rapor ve Sistem bölümünden "Bildirim Genel Yönetimi" yetkisini açın.',
            ],
            'notification_rules.manage' => [
                'label' => 'Bildirim Kurallarını Yönet',
                'group' => 'Bildirim, Rapor ve Sistem',
                'description' => 'Hangi olayda kime bildirim gideceğini düzenleyebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Bildirim, Rapor ve Sistem bölümünden "Bildirim Kurallarını Yönet" yetkisini açın.',
            ],
            'notification_channels.manage' => [
                'label' => 'Kanal Ayarlarını Yönet',
                'group' => 'Bildirim, Rapor ve Sistem',
                'description' => 'Mail, Telegram ve WhatsApp kanal ayarlarını düzenleyebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Bildirim, Rapor ve Sistem bölümünden "Kanal Ayarlarını Yönet" yetkisini açın.',
            ],
            'mail_templates.manage' => [
                'label' => 'Mail Şablonlarını Yönet',
                'group' => 'Bildirim, Rapor ve Sistem',
                'description' => 'Mail konu ve içerik şablonlarını düzenleyebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Bildirim, Rapor ve Sistem bölümünden "Mail Şablonlarını Yönet" yetkisini açın.',
            ],
            'reports.view' => [
                'label' => 'Rapor ve Kayıt Listesini Gör',
                'group' => 'Bildirim, Rapor ve Sistem',
                'description' => 'Giriş çıkış kayıtlarını, filtreleri ve PDF indirmeyi kullanabilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Bildirim, Rapor ve Sistem bölümünden "Rapor ve Kayıt Listesini Gör" yetkisini açın.',
            ],
            'reports.manage' => [
                'label' => 'Planlı Raporları Yönet',
                'group' => 'Bildirim, Rapor ve Sistem',
                'description' => 'Rapor planı oluşturabilir, düzenleyebilir ve çalıştırabilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Bildirim, Rapor ve Sistem bölümünden "Planlı Raporları Yönet" yetkisini açın.',
            ],
            'backups.manage' => [
                'label' => 'Yedeklemeyi Yönet',
                'group' => 'Bildirim, Rapor ve Sistem',
                'description' => 'Yedek alma, indirme ve plan yönetimini kullanabilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Bildirim, Rapor ve Sistem bölümünden "Yedeklemeyi Yönet" yetkisini açın.',
            ],
            'suggestions.manage' => [
                'label' => 'Kullanıcı Önerilerini Yönet',
                'group' => 'Bildirim, Rapor ve Sistem',
                'description' => 'Personel önerilerini takip edip durumlarını değiştirebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Bildirim, Rapor ve Sistem bölümünden "Kullanıcı Önerilerini Yönet" yetkisini açın.',
            ],
            'settings.manage' => [
                'label' => 'Kurulum ve Sistem Ayarları',
                'group' => 'Bildirim, Rapor ve Sistem',
                'description' => 'Kurulum ekranı, sistem ayarları ve global eskalasyon zincirini yönetebilir.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında Bildirim, Rapor ve Sistem bölümünden "Kurulum ve Sistem Ayarları" yetkisini açın.',
            ],
        ];
    }

    public static function userPermissionOptions(): array
    {
        $items = [];
        foreach (self::items() as $code => $item) {
            $items[] = [
                'code' => $code,
                'group' => $item['group'],
                'label' => $item['label'],
                'description' => $item['description'],
            ];
        }

        return $items;
    }

    public static function presets(): array
    {
        return [
            [
                'label' => 'Güvenlik',
                'description' => 'Kapı giriş/çıkış işlemleri için temel set.',
                'codes' => [
                    'dashboard.view',
                    'dashboard.block.door',
                    'dashboard.block.inside',
                    'dashboard.block.activity',
                    'dashboard.block.stats',
                    'dashboard.view_settings',
                    'visits.create_entry',
                    'visits.create_exit',
                    'visits.view_all',
                ],
            ],
            [
                'label' => 'Departman Amiri',
                'description' => 'Süre aşımı sorularını cevaplar, kayıtları görür.',
                'codes' => [
                    'dashboard.view',
                    'dashboard.block.inside',
                    'dashboard.block.activity',
                    'dashboard.block.stats',
                    'dashboard.block.verifications',
                    'dashboard.view_settings',
                    'visits.view_all',
                    'visits.department_verify',
                    'reports.view',
                ],
            ],
            [
                'label' => 'Operasyon Müdürü',
                'description' => 'Canlı kayıtları takip eder, süre aşımı onayı verir, rapor ve departmanları görür.',
                'codes' => [
                    'dashboard.view',
                    'dashboard.block.door',
                    'dashboard.block.inside',
                    'dashboard.block.activity',
                    'dashboard.block.stats',
                    'dashboard.block.verifications',
                    'dashboard.view_settings',
                    'visits.create_entry',
                    'visits.create_exit',
                    'visits.view_all',
                    'visits.department_verify',
                    'reports.view',
                    'departments.manage',
                    'notifications.manage',
                    'notification_rules.manage',
                ],
            ],
            [
                'label' => 'Yönetici',
                'description' => 'Kurulum dışındaki operasyon ve yönetim ekranlarının tamamı.',
                'codes' => array_values(array_diff(array_keys(self::items()), ['settings.manage'])),
            ],
            [
                'label' => 'Tam Yetki',
                'description' => 'Tüm operasyon, yönetim, yedekleme ve sistem ayarları.',
                'codes' => array_keys(self::items()),
            ],
        ];
    }

    public static function advice(array|string|null $requiredPermission): array
    {
        $codes = array_values(array_filter(array_map('strval', (array) $requiredPermission)));
        $items = self::items();
        $missing = [];

        foreach ($codes as $code) {
            $item = $items[$code] ?? [
                'label' => $code,
                'description' => 'Bu işlem için gerekli özel yetki.',
                'grant_hint' => 'Yönetim > Kullanıcı ve Yetki ekranında bu kullanıcıya ilgili yetki verilmelidir.',
            ];
            $missing[] = [
                'code' => $code,
                'label' => $item['label'],
                'description' => $item['description'],
                'grant_hint' => $item['grant_hint'],
            ];
        }

        return [
            'missing' => $missing,
            'summary' => count($missing) > 1
                ? 'Bu işlem için aşağıdaki yetkilerden en az biri gereklidir.'
                : 'Bu işlem için aşağıdaki yetki gereklidir.',
        ];
    }
}
