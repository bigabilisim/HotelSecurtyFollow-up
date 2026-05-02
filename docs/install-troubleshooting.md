# Kurulum Sorun Giderme

## Document Root Nedir?

Document root, web sunucusunun dışarıya açtığı klasördür. Bu projede domain doğrudan `public/` klasörüne bakmalıdır.

Doğru yapı:

```text
/var/www/otel-guvenlik/app
/var/www/otel-guvenlik/config
/var/www/otel-guvenlik/database
/var/www/otel-guvenlik/public/index.php
/var/www/otel-guvenlik/storage
```

Domain ayarı:

```text
DocumentRoot /var/www/otel-guvenlik/public
```

## Apache Örneği

```apache
<VirtualHost *:80>
    ServerName alanadiniz.com
    DocumentRoot /var/www/otel-guvenlik/public

    <Directory /var/www/otel-guvenlik/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Apache için `mod_rewrite` açık olmalıdır. Değilse `/setup` çalışmayabilir; bu durumda geçici olarak şu adres kullanılabilir:

```text
https://alanadiniz.com/index.php?route=/setup
```

## Nginx Örneği

```nginx
server {
    server_name alanadiniz.com;
    root /var/www/otel-guvenlik/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
```

## cPanel / Plesk

Domain veya subdomain ayarında belge kökü şu klasör olmalıdır:

```text
otel-guvenlik/public
```

Panel buna izin vermiyorsa en temiz çözüm bir subdomain açıp document root alanını `public` klasörüne vermektir.

Bu mümkün değilse paketin kökündeki `.htaccess` ve `index.php` fallback dosyaları proje kökünden gelen istekleri `public/index.php` içine yönlendirir. Yine de güvenlik ve temizlik açısından önerilen yöntem document root değerini `public/` yapmaktır.

## Hızlı Kontrol

- `https://alanadiniz.com/setup`
- `https://alanadiniz.com/index.php?route=/setup`
- `https://alanadiniz.com/assets/app.css`

İlk iki adresten biri kurulum ekranını açmalı, üçüncü adres CSS dosyasını göstermelidir.

## Kurulum Ekranında Girilecekler

Canlı kurulum sihirbazı yalnızca SQL bağlantısı ve ilk admin hesabını ister:

- SQL host ve port
- Veritabanı adı
- SQL kullanıcı adı ve şifre
- İlk admin ad soyad, kullanıcı adı, e-posta ve şifre

SQL kullanıcısının veritabanı oluşturma yetkisi varsa sihirbaz veritabanını oluşturur. Yetki yoksa veritabanını panelinizden boş olarak açın; sihirbaz tabloları ve başlangıç verilerini yine otomatik yükler.

## Eski/Yarım Kurulumdan Devam Etme

Daha önce aynı veritabanında eski bir deneme yapıldıysa bazı tablolar oluşmuş, fakat yeni kolonlar eklenmemiş olabilir. Bu durumda eski paketlerde şu tip hata görülebilir:

```text
Unknown column 'is_quick_access' in 'INSERT INTO'
```

Güncel sihirbaz `schema.sql` dosyasından sonra eksik kolonları otomatik onarır ve ardından `seed.sql` dosyasını çalıştırır. Bu yüzden veritabanını silmeden, güncel paketi yükleyip `/setup` ekranında aynı SQL bilgileriyle tekrar kurulum denenebilir.
