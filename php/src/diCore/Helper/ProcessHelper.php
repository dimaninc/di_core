<?php

namespace diCore\Helper;

/**
 * Внешний процесс с жёстким дедлайном.
 *
 * `exec()` таймаута не имеет, а `set_time_limit()` на Unix не считает время,
 * проведённое в системных вызовах, — то есть от зависшего конвертера не
 * спасает вообще. Под FPM это означает воркер, занятый до упора: при
 * `pm.max_children = 30` десяток таких запросов кладёт весь пул.
 */
class ProcessHelper
{
    /** сколько ждать после SIGTERM, прежде чем слать SIGKILL */
    const TERM_GRACE_SEC = 2;

    /** пауза цикла опроса, микросекунды */
    const POLL_INTERVAL_USEC = 20000;

    /**
     * Потолок на собранный вывод. Это чужой бинарь: сколько он напишет в
     * stderr, не решает никто, а строка целиком уезжает в сообщение исключения
     * и оттуда в лог. Обрезаем, а не копим.
     */
    const MAX_OUTPUT_BYTES = 16384;

    /** тот же код, что отдаёт coreutils timeout */
    const EXIT_TIMED_OUT = 124;

    /** процесс не удалось запустить */
    const EXIT_NOT_STARTED = -1;

    /**
     * @param string|string[] $command Массив – это execvp без шелла: ни
     *   экранирования, ни разбора кавычек, и SIGTERM приходит самому бинарю.
     *   Строка идёт через `sh -c` – см. shellForm() и оговорку в шапке класса.
     * @param float $timeoutSec Дедлайн на ЗАПУЩЕННЫЙ процесс. Реальное время
     *   возврата – до `$timeoutSec + TERM_GRACE_SEC`: убитому процессу даётся
     *   пауза между SIGTERM и SIGKILL. Закладывайте её в свой бюджет.
     * @param array<string,string>|null $env Добавка к окружению процесса (секреты –
     *   сюда, а не в argv: аргументы видны в `ps`). Остальное окружение наследуется.
     * @param array{stdin?:string,stdout?:string}|null $files Файлы вместо труб: stdin читается
     *   из файла, stdout пишется в файл (тогда в `output` попадает только stderr). Так
     *   бинарь вроде `docker exec -i … pg_dump` пишет дамп на хост, а не внутрь контейнера.
     * @return array{code:int, output:string[], timedOut:bool}
     */
    public static function run(
        $command,
        float $timeoutSec,
        ?array $env = null,
        ?array $files = null
    ): array {
        if (!function_exists('proc_open')) {
            return static::runWithoutTimeout($command, $env, $files);
        }

        // stderr сливается в stdout ядром, а не склеивается нами после: иначе
        // строки шли бы не в том порядке, в каком их написала утилита, и режим
        // с proc_open расходился бы с деградацией через `2>&1`
        $descriptors = [
            0 => isset($files['stdin']) ? ['file', $files['stdin'], 'r'] : ['pipe', 'r'],
            1 => isset($files['stdout']) ? ['file', $files['stdout'], 'w'] : ['pipe', 'w'],
            2 => isset($files['stdout']) ? ['pipe', 'w'] : ['redirect', 1],
        ];
        // труба, из которой собирается вывод
        $outIdx = isset($files['stdout']) ? 2 : 1;
        $pipes = [];
        $process = @proc_open(
            is_array($command) ? $command : static::shellForm($command),
            $descriptors,
            $pipes,
            null,
            $env === null ? null : array_merge(static::currentEnv(), $env)
        );

        if (!is_resource($process)) {
            return [
                'code' => self::EXIT_NOT_STARTED,
                'output' => ['unable to start process'],
                'timedOut' => false,
            ];
        }

        isset($pipes[0]) && fclose($pipes[0]);
        // без этого чтение блокируется до конца процесса, и весь дедлайн
        // оказывается декорацией
        stream_set_blocking($pipes[$outIdx], false);

        $deadline = microtime(true) + max(0.0, $timeoutSec);
        $buffer = '';
        $timedOut = false;
        $code = self::EXIT_NOT_STARTED;

        while (true) {
            // вычитываем на каждом обороте: полный буфер трубы остановил бы
            // процесс намертво, и он бы не завершился никогда — читать надо
            // всегда, даже когда собранное уже не нужно
            $buffer = static::clamp($buffer . static::drain([1 => $pipes[$outIdx]]));

            $status = proc_get_status($process);

            if (!$status['running']) {
                // exitcode достоверен только при первом переходе в
                // «не running»; дальше proc_get_status отдаёт -1
                $code = (int) $status['exitcode'];
                break;
            }

            if (microtime(true) >= $deadline) {
                $timedOut = true;
                $code = self::EXIT_TIMED_OUT;
                static::kill($process);
                break;
            }

            usleep(static::POLL_INTERVAL_USEC);
        }

        $buffer = static::clamp($buffer . static::drain([1 => $pipes[$outIdx]]));

        fclose($pipes[$outIdx]);
        proc_close($process);

        return [
            'code' => $code,
            'output' => static::splitOutput($buffer),
            'timedOut' => $timedOut,
        ];
    }

    /**
     * proc_open в disabled_functions – деградируем до прежнего поведения:
     * без таймаута, но с конвертацией. Отказ конвертировать вообще хуже.
     *
     * @param string|string[] $command
     * @param array<string,string>|null $env
     * @param array|null $files см. run()
     * @return array{code:int, output:string[], timedOut:bool}
     */
    protected static function runWithoutTimeout($command, ?array $env = null, ?array $files = null): array
    {
        if (!function_exists('exec')) {
            return [
                'code' => self::EXIT_NOT_STARTED,
                'output' => ['exec() and proc_open() are both disabled'],
                'timedOut' => false,
            ];
        }

        $line = is_array($command)
            ? join(' ', array_map('escapeshellarg', $command))
            : $command;
        $output = [];

        // 2>&1 обязателен: причину отказа утилиты пишут в stderr, а exec()
        // забирает только stdout
        // exec наследует окружение процесса: добавку ставим на время вызова
        $saved = [];

        foreach ($env ?? [] as $name => $value) {
            $saved[$name] = getenv($name);
            putenv("$name=$value");
        }

        if (isset($files['stdin'])) {
            $line .= ' < ' . escapeshellarg($files['stdin']);
        }

        if (isset($files['stdout'])) {
            // stderr – во временный файл, чтобы не смешаться с содержимым stdout-файла
            $errFile = tempnam(sys_get_temp_dir(), 'proc');
            exec($line . ' > ' . escapeshellarg($files['stdout']) . ' 2>' . escapeshellarg($errFile), $unused, $code);
            $output = file($errFile, FILE_IGNORE_NEW_LINES) ?: [];
            unlink($errFile);
        } else {
            exec("$line 2>&1", $output, $code);
        }

        foreach ($saved as $name => $value) {
            putenv($value === false ? $name : "$name=$value");
        }

        return [
            'code' => (int) $code,
            'output' => static::splitOutput(static::clamp(join("\n", $output))),
            'timedOut' => false,
        ];
    }

    protected static function currentEnv(): array
    {
        return getenv() ?: [];
    }

    /**
     * `exec` заставляет шелл подменить себя бинарём, и тогда сигнал доходит до
     * настоящего процесса, а не до обёртки. Но только для ОДИНОЧНОЙ команды: в
     * `a; b` или `a | b` префикс поменял бы смысл строки — шелл подменился бы
     * первой командой, и остальное просто не выполнилось бы.
     */
    protected static function shellForm(string $command): string
    {
        return preg_match('/[;&|<>()`\n]/', $command)
            ? $command
            : 'exec ' . $command;
    }

    /**
     * Хвост важнее начала: причина отказа у консольных утилит идёт последней
     * строкой, а первые — обычно баннер и предупреждения.
     */
    protected static function clamp(string $buffer): string
    {
        if (strlen($buffer) <= static::MAX_OUTPUT_BYTES) {
            return $buffer;
        }

        return '…' . substr($buffer, -static::MAX_OUTPUT_BYTES);
    }

    /** @param resource[] $pipes */
    protected static function drain(array $pipes): string
    {
        return (string) stream_get_contents($pipes[1]);
    }

    /**
     * SIGTERM, и только потом SIGKILL: утилите даётся шанс убрать за собой
     * недописанный файл.
     *
     * @param resource $process
     */
    protected static function kill($process): void
    {
        @proc_terminate($process);

        $until = microtime(true) + static::TERM_GRACE_SEC;

        while (microtime(true) < $until) {
            $status = proc_get_status($process);

            if (!$status['running']) {
                return;
            }

            usleep(static::POLL_INTERVAL_USEC);
        }

        @proc_terminate($process, 9);
    }

    /** @return string[] */
    protected static function splitOutput(string $buffer): array
    {
        $buffer = trim(str_replace("\r\n", "\n", $buffer));

        return $buffer === '' ? [] : explode("\n", $buffer);
    }
}
