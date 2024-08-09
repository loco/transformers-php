<?php

declare(strict_types=1);

namespace Codewithkyrian\Transformers\FFI\Libs;

use Codewithkyrian\Transformers\FFI\Lib;
use Codewithkyrian\Transformers\Transformers;
use Exception;
use FFI;
use FFI\CData;
use RuntimeException;
use function Codewithkyrian\Transformers\Utils\joinPaths;

class Sndfile
{
    protected static FFI $ffi;

    protected const LIBRARY_NAME = 'libsndfile';

    protected const INSTALLER_COMMANDS = [
        'brew' => 'brew install libsndfile',
        'macports' => 'port install libsndfile',
        'apt' => 'apt install libsndfile1',
        'apk' => 'apk add libsndfile',
        'yum' => 'yum install libsndfile',
        'choco' => 'choco install libsndfile',
        'scoop' => 'scoop install libsndfile',
    ];

    public static function getInstallerSuggestion(): ?string
    {
        $libName = self::LIBRARY_NAME;
        $resolver = Transformers::getLibraryResolver();
        $installer = $resolver->inferInstaller();

        $installerCommand = self::INSTALLER_COMMANDS[$installer] ?? null;

        if ($installerCommand) {
            return "Try running `$installerCommand` to install the library. "
                ."If you don't want to install it on a system level, "
                ."you can install it manually by running: `./vendor/bin/transformers install $libName`";
        }

        return "Install the library manually by running: `./vendor/bin/transformers install $libName`";
    }

    /**
     * Returns an instance of the FFI class after checking if it has already been instantiated.
     * If not, it creates a new instance by defining the header contents and library path.
     *
     * @return FFI The FFI instance.
     * @throws Exception
     */
    protected static function ffi(): FFI
    {
        if (!isset(self::$ffi)) {
            self::$ffi = FFI::cdef(
                file_get_contents(Lib::Sndfile->header()),
                Lib::Sndfile->library()
            );
        }

        return self::$ffi;
    }

    /**
     * Creates a new instance of the specified type.
     *
     * @param string $type The type of the instance to create.
     * @param bool $owned Whether the instance should be owned. Default is true.
     * @param bool $persistent Whether the instance should be persistent. Default is false.
     *
     * @return CData|null The created instance, or null if the creation failed.
     * @throws Exception
     */
    public static function new(string $type, bool $owned = true, bool $persistent = false): ?CData
    {
        return self::ffi()->new($type, $owned, $persistent);
    }

    /**
     * Retrieves the value of the enum constant with the given name.
     *
     * @param string $name The name of the enum constant.
     *
     * @return mixed The value of the enum constant.
     * @throws Exception
     */
    public static function enum(string $name): mixed
    {
        return self::ffi()->{$name};
    }

    /**
     * Returns the version of the library as a string.
     *
     * @return string The version of the library.
     */
    public static function version(): string
    {
        return self::ffi()->sf_version_string();
    }

    /**
     * Opens a file for read, write or both, depending on the specified mode.
     *
     * @param string $path The path to the file.
     * @param int $mode The mode in which to open the file (e.g., read, write, read/write).
     * @param CData $sfinfo The structure containing information about the file.
     *
     * @return mixed The handle to the opened file.
     * @throws RuntimeException|Exception If the file fails to open.
     */
    public static function open(string $path, int $mode, CData $sfinfo): mixed
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $sndfile = self::ffi()->sf_wchar_open($path, $mode, $sfinfo);
        } else {
            $sndfile = self::ffi()->sf_open($path, $mode, $sfinfo);
        }

        if ($sndfile === null) {
            $error = self::ffi()->sf_strerror($sndfile);
            throw new RuntimeException("Failed to open file: $error");
        }

        return $sndfile;
    }

    /**
     * Retrieves the format information of a sound file.
     *
     * @param mixed $sndfile The sound file object.
     * @param mixed $sfinfo The sound file info object.
     *
     * @return string The name of the format.
     */
    public static function getFormat(mixed $sndfile, mixed $sfinfo): string
    {
        $info = self::ffi()->new('SF_FORMAT_INFO');

        $info->format = $sfinfo->format;

        self::ffi()->sf_command($sndfile, self::ffi()->SFC_GET_FORMAT_INFO, FFI::addr($info), FFI::sizeof($info));

        return $info->name;
    }

    /**
     * Reads frames from a sound file.
     *
     * @param CData $sndfile The sound file to read from.
     * @param CData $ptr The pointer to the data buffer.
     * @param int $frames The number of frames to read.
     * @param string $type The type of data to read. Defaults to 'float'.
     *
     * @return int The number of frames read.
     * @throws Exception
     */
    public static function readFrames(CData $sndfile, CData $ptr, int $frames, string $type = 'float'): int
    {
        return match ($type) {
            'int' => self::ffi()->sf_readf_int($sndfile, $ptr, $frames),
            'float' => self::ffi()->sf_readf_float($sndfile, $ptr, $frames),
            'double' => self::ffi()->sf_readf_double($sndfile, $ptr, $frames),
        };
    }

    /**
     * Writes frames to a sound file.
     *
     * @param CData $sndfile The sound file to write to.
     * @param CData $ptr The pointer to the data buffer.
     * @param int $frames The number of frames to write.
     * @param string $type The type of data to write. Defaults to 'float'.
     *
     * @return int The number of frames written.
     * @throws Exception
     */
    public static function writeFrames(CData $sndfile, CData $ptr, int $frames, string $type = 'float'): int
    {
        return match ($type) {
            'int' => self::ffi()->sf_writef_int($sndfile, $ptr, $frames),
            'float' => self::ffi()->sf_writef_float($sndfile, $ptr, $frames),
            'double' => self::ffi()->sf_writef_double($sndfile, $ptr, $frames),
        };
    }


    public static function close(CData $sndfile): void
    {
        self::ffi()->sf_close($sndfile);
    }
}