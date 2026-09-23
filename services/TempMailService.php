<?php
/**
 * Temp Mail Service
 * Manages instant temporary email provisioning, Ethereal SMTP sync,
 * in-app temp inbox storage, and webmail links.
 */

class TempMailService
{
    private static string $storageFile = __DIR__ . '/../storage/temp_mail_account.json';
    private static string $tempInboxDir = __DIR__ . '/../storage/mailbox/temp';

    /**
     * Get active temp mail account or create one if none exists.
     */
    public static function getOrCreateAccount(): array
    {
        if (file_exists(self::$storageFile)) {
            $data = json_decode(file_get_contents(self::$storageFile), true);
            if (!empty($data['email']) && !empty($data['password'])) {
                return $data;
            }
        }

        return self::createNewAccount();
    }

    /**
     * Generate a new temporary Ethereal account via official API.
     */
    public static function createNewAccount(): array
    {
        $dir = dirname(self::$storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $account = null;
        try {
            $ch = curl_init('https://api.nodemailer.com/user');
            $payload = json_encode(['requestor' => 'gri-portal-user', 'version' => '1.0']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $resData = json_decode($response, true);
                if (!empty($resData['user']) && !empty($resData['pass'])) {
                    $account = [
                        'email'        => $resData['user'],
                        'username'     => $resData['user'],
                        'password'     => $resData['pass'],
                        'smtp_host'    => $resData['smtp']['host'] ?? 'smtp.ethereal.email',
                        'smtp_port'    => (int)($resData['smtp']['port'] ?? 587),
                        'web_url'      => 'https://ethereal.email/messages',
                        'created_at'   => date('Y-m-d H:i:s'),
                        'provider'     => 'ethereal'
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log("Failed to create online ethereal account: " . $e->getMessage());
        }

        // Fallback to local virtual temp mailbox if online API fails
        if (!$account) {
            $uniqueId = substr(md5(uniqid('', true)), 0, 8);
            $account = [
                'email'        => "temp_{$uniqueId}@ethereal.email",
                'username'     => "temp_{$uniqueId}@ethereal.email",
                'password'     => bin2hex(random_bytes(8)),
                'smtp_host'    => 'smtp.ethereal.email',
                'smtp_port'    => 587,
                'web_url'      => 'https://ethereal.email',
                'created_at'   => date('Y-m-d H:i:s'),
                'provider'     => 'virtual'
            ];
        }

        file_put_contents(self::$storageFile, json_encode($account, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $account;
    }

    /**
     * Check if a given recipient email belongs to the active temp account.
     */
    public static function isTempRecipient(string $email): bool
    {
        $account = self::getOrCreateAccount();
        $target = strtolower(trim($email));
        $activeEmail = strtolower(trim($account['email'] ?? ''));

        if ($target === $activeEmail) return true;
        if (str_contains($target, 'ethereal.email')) return true;
        if (str_contains($target, 'temp') && str_contains($target, '@')) return true;

        return false;
    }

    /**
     * Save an incoming message to the Temp Mailbox repository.
     */
    public static function saveIncomingMessage(array $msg): void
    {
        if (!is_dir(self::$tempInboxDir)) {
            mkdir(self::$tempInboxDir, 0755, true);
        }

        $id = $msg['id'] ?? ('tmp_' . date('Ymd_His_') . bin2hex(random_bytes(4)));
        $filename = self::$tempInboxDir . '/' . date('Ymd_His_') . $id . '.json';

        $account = self::getOrCreateAccount();
        $msg['webmail_url'] = $account['web_url'] ?? 'https://ethereal.email/messages';

        file_put_contents($filename, json_encode($msg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Retrieve all messages in the Temp Mailbox.
     */
    public static function getInbox(int $limit = 40): array
    {
        if (!is_dir(self::$tempInboxDir)) {
            mkdir(self::$tempInboxDir, 0755, true);
            return [];
        }

        $files = glob(self::$tempInboxDir . '/*.json');
        if (empty($files)) return [];

        rsort($files); // Newest first
        $files = array_slice($files, 0, $limit);

        $messages = [];
        foreach ($files as $f) {
            $content = @file_get_contents($f);
            if ($content) {
                $item = json_decode($content, true);
                if ($item) $messages[] = $item;
            }
        }

        return $messages;
    }

    /**
     * Clear all temp inbox messages.
     */
    public static function clearInbox(): bool
    {
        if (!is_dir(self::$tempInboxDir)) return true;
        $files = glob(self::$tempInboxDir . '/*.json');
        foreach ($files as $f) {
            @unlink($f);
        }
        return true;
    }
}
