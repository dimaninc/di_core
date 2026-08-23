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
     * @return array{code:int, output:string[], timedOut:bool}
     */
    public static function run($command, float $timeoutSec): array
    {
        if (!function_exists('proc_open')) {
            return static::runWithoutTimeout($command);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = @proc_open(
            is_array($command) ? $command : static::shellForm($command),
            $descriptors,
            $pipes
        );

        if (!is_resource($process)) {
            return [
                'code' => self::EXIT_NOT_STARTED,
                'output' => ['unable to start process'],
                'timedOut' => false,
            ];
        }

        fclose($pipes[0]);
        // без этого чтение блокируется до конца процесса, и весь дедлайн
        // оказывается декорацией
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = microtime(true) + max(0.0, $timeoutSec);
        $buffer = '';
        $timedOut = false;
        $code = self::EXIT_NOT_STARTED;

        while (true) {
            // вычитываем на каждом обороте: полный буфер трубы остановил бы
            // процесс намертво, и он бы не завершился никогда — читать надо
            // всегда, даже когда собранное уже не нужно
            $buffer = static::clamp($buffer . static::drain($pipes));

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

        $buffer = static::clamp($buffer . static::drain($pipes));

        fclose($pipes[1]);
        fclose($pipes[2]);
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
     * @return array{code:int, output:string[], timedOut:bool}
     */
    protected static function runWithoutTimeout($command): array
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
        exec("$line 2>&1", $output, $code);

        return [
            'code' => (int) $code,
            'output' => static::splitOutput(static::clamp(join("\n", $output))),
            'timedOut' => false,
        ];
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
        return (string) stream_get_contents($pipes[1]) .
            (string) stream_get_contents($pipes[2]);
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
