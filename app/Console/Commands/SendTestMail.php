<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Отправляет тестовое письмо для проверки параметров MAIL_ (mailer, host, port,
 * учётные данные, отправитель). Печатает действующую конфигурацию и явно
 * сообщает об успехе или об ошибке транспорта с текстом исключения.
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test
        {to? : Адрес получателя (по умолчанию — MAIL_FROM_ADDRESS)}
        {--subject= : Тема письма (по умолчанию — служебная)}';

    protected $description = 'Отправить тестовое письмо для проверки настроек MAIL_';

    public function handle(): int
    {
        $to = $this->argument('to') ?: config('mail.from.address');

        if (empty($to)) {
            $this->error('Не задан получатель: укажите адрес аргументом или заполните MAIL_FROM_ADDRESS.');

            return self::FAILURE;
        }

        $this->showConfig($to);

        $subject = $this->option('subject')
            ?: 'Проверка почты · '.config('app.name').' · '.now()->format('d.m.Y H:i:s');

        $body = "Это тестовое письмо от «".config('app.name')."».\n"
            ."Если вы его получили — параметры MAIL_ настроены верно.\n\n"
            .'Отправлено: '.now()->toDateTimeString().' ('.config('app.timezone').").\n"
            .'Mailer: '.config('mail.default').', host: '.config('mail.mailers.smtp.host').'.';

        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Не удалось отправить письмо: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        if (config('mail.default') === 'log') {
            $this->warn('Mailer=log — письмо записано в лог (storage/logs), реальная отправка не выполнялась.');
        } else {
            $this->info("Письмо отправлено на {$to}.");
        }

        return self::SUCCESS;
    }

    /**
     * Вывести действующие параметры MAIL_ (пароль маскируется).
     */
    private function showConfig(string $to): void
    {
        $smtp = config('mail.mailers.smtp');

        $this->line('<comment>Действующие параметры почты:</comment>');
        $this->table(['Параметр', 'Значение'], [
            ['MAILER', config('mail.default')],
            ['HOST', $smtp['host'] ?? '—'],
            ['PORT', $smtp['port'] ?? '—'],
            ['ENCRYPTION', $smtp['encryption'] ?? '—'],
            ['USERNAME', $smtp['username'] ?? '—'],
            ['PASSWORD', empty($smtp['password']) ? '—' : str_repeat('*', 8)],
            ['FROM', trim((config('mail.from.name') ?? '').' <'.config('mail.from.address').'>')],
            ['TO', $to],
        ]);
    }
}
