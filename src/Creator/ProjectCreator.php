<?php declare(strict_types=1);

namespace SwoftLabs\Devtool\Creator;

use InvalidArgumentException;
use Swoft\Stdlib\Helper\Dir;
use Swoft\Stdlib\Helper\Sys;
use function array_filter;
use function basename;
use function file_exists;
use function strlen;
use function strpos;
use function trim;

/**
 * class ProjectCreator
 */
class ProjectCreator extends AbstractCreator
{
    public const GITHUB_URL = 'https://github.com';

    public const SWOFT_CLOUD_URL = 'https://github.com/swoft-cloud';

    // https://github.com/swoft-cloud/swoft-http-project.git
    // git@github.com:swoft-cloud/swoft-http-project.git
    public const DEMO_GITHUB_REPOS = [
        'http' => 'swoft-http-project',
        'tcp'  => 'swoft-tcp-project',
        'rpc'  => 'swoft-rpc-project',
        'ws'   => 'swoft-ws-project',
        'full' => 'swoft',
    ];

    /**
     * type name
     *
     * @var string
     */
    private $type = '';

    /**
     * Repository name or repository url
     *
     * @var string
     */
    private $repo = '';

    /**
     * Repository github url
     *
     * @var string
     */
    private $repoUrl = '';

    /**
     * Project path
     *
     * @var string
     */
    private $projectPath = '';

    /**
     * @var bool
     */
    private $refresh = false;

    public static function new(array $config = [])
    {
        return new self($config);
    }

    public function validate(): bool
    {
        if (!$this->name) {
            $this->error = 'please set the new project name';
            return false;
        }

        if ($repo = $this->repo) {
            if ($this->isFullUrl($repo)) { // full github url
                $repoUrl = $repo;
            } elseif (strpos($repo, '/')) { // user/repo-name
                $repoUrl = self::GITHUB_URL . '/' . $repo . '.git';
            } else {
                $this->error = "invalid 'repo' address: $repo";
                return false;
            }
        } elseif ($type = $this->type) {
            if ($this->isValidType($type)) {
                $repoName = self::DEMO_GITHUB_REPOS[$type];
                $repoUrl = self::SWOFT_CLOUD_URL . '/' . $repoName . '.git';
            } else {
                $this->error = "invalid 'type' name: {$type}, allow: http, ws, tcp, rpc";
                return false;
            }
        } else {
            $this->error = "missing 'repo' or 'type' setting";
            return false;
        }

        $this->repoUrl = $repoUrl;

        $this->projectPath = $this->workDir ? $this->workDir . '/' . $this->name : $this->name;
        return true;
    }

    /**
     * @return array
     */
    public function getInfo(): array
    {
        $info = [
            'name'     => $this->name,
            'type'     => $this->type,
            'repo'     => $this->repo,
            'repoUrl'  => $this->repoUrl,
            'workDir'  => $this->workDir,
            'projectPath'  => $this->projectPath,
        ];

        return array_filter($info);
    }

    public function create(): void
    {
        if ($this->error) {
            return;
        }

        $path = $this->projectPath;
        if (file_exists($path)) {
            $this->error = 'the project dir has been exist!';
            return;
        }

        $this->notifyMessage('Begin create the new project: ' . $this->getName());
        $tmpDir = Sys::getTempDir() . '/swoft-app-demos';
        if (!file_exists($tmpDir)) {
            Dir::make($tmpDir, 0775);
        }

        $refresh = $this->refresh;
        $dirName = basename($this->repoUrl, '.git');
        $dirPath = $tmpDir . '/' . $dirName;
        $hasExist = file_exists($dirPath);

        if ($hasExist && $refresh) {
            $this->deleteDir($dirPath);
            if ($this->error) {
                return;
            }
        }

        if (!$hasExist) {
            $this->notifyMessage('Clone Repo ' . $this->repoUrl);

            $cmd = "cd $tmpDir && git clone --depth 1 {$this->repoUrl}";
            if (!$this->exec($cmd)) {
                return;
            }
        }

        $this->notifyMessage('Copy project files from local cache');

        $this->notifyMessage('Remove .git directory on new project');
        $cmd = "cp -R $dirPath $path && rm -rf {$path}/.git";
        if (!$this->exec($cmd)) {
            return;
        }

        $this->notifyMessage("Project: $path created");
    }

    /**
     * install by composer
     *
     * @param bool $noDev
     */
    public function install(bool $noDev = false): void
    {
        $this->notifyMessage('Begin run composer install for init project');

        $path = $this->projectPath;
        $cmd  = "cd $path && composer install";

        if ($noDev) {
            $cmd .= ' --no-dev';
        }

        if (!$this->exec($cmd)) {
            return;
        }

        $this->notifyMessage('Composer packages install complete');
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function deleteDir(string $path): bool
    {
        if (strlen($path) < 6) {
            throw new InvalidArgumentException('path is to short, cannot exec rm', 500);
        }

        $this->notifyMessage('Delete Dir: ' . $path);

        $cmd = "rm -rf $path";
        return $this->exec($cmd);
    }

    /**
     * @param string $str
     * @return boolean
     */
    public function isFullUrl(string $str): bool
    {
        if (strpos($str, 'http:') === 0) {
            return true;
        }

        if (strpos($str, 'https:') === 0) {
            return true;
        }

        if (strpos($str, 'git@') === 0) {
            return true;
        }

        return false;
    }

    /**
     * @param string $type
     * @return boolean
     */
    public function isValidType(string $type): bool
    {
        return isset(self::DEMO_GITHUB_REPOS[$type]);
    }

    /**
     * Get type name
     *
     * @return  string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set type name
     *
     * @param  string  $type  type name
     *
     * @return  self
     */
    public function setType(string $type): self
    {
        if ($type = trim($type)) {
            $this->type = $type;
        }

        return $this;
    }

    /**
     * Get repo name or url
     *
     * @return  string
     */
    public function getRepo(): string
    {
        return $this->repo;
    }

    /**
     * Set repo name or url
     *
     * @param  string  $repo  Repo name or url
     *
     * @return  self
     */
    public function setRepo(string $repo): self
    {
        if ($repo = trim($repo)) {
            $this->repo = $repo;
        }

        return $this;
    }

    /**
     * Get the value of projectPath
     * @return  string
     */
    public function getProjectPath(): string
    {
        return $this->projectPath;
    }

    /**
     * Get the value of refresh
     *
     * @return  bool
     */
    public function getRefresh(): bool
    {
        return $this->refresh;
    }

    /**
     * Set the value of refresh
     *
     * @param  bool  $refresh
     *
     * @return  self
     */
    public function setRefresh(bool $refresh): self
    {
        $this->refresh = $refresh;

        return $this;
    }
}
