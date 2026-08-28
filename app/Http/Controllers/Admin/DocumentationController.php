<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentationController extends Controller
{
    protected string $docsPath;

    public function __construct()
    {
        $this->docsPath = base_path('docs');
    }

    public function index(Request $request)
    {
        $pages = $this->getPages();

        return view('admin.documentation.index', [
            'title' => 'Documentation',
            'section' => 'Platform Documentation',
            'pages' => $pages,
        ]);
    }

    public function show(Request $request, string $path)
    {
        $absolute = $this->resolvePath($path);

        if (! $absolute) {
            throw new NotFoundHttpException('Documentation page not found.');
        }

        $content = File::get($absolute);
        $html = $this->renderMarkdown($content);
        $title = $this->extractTitle($content) ?? 'Documentation';
        $pages = $this->getPages();
        $rawText = strip_tags($html);

        return view('admin.documentation.show', [
            'title' => $title,
            'section' => 'Platform Documentation',
            'html' => $html,
            'title' => $title,
            'path' => $path,
            'pages' => $pages,
            'rawText' => $rawText,
        ]);
    }

    protected function resolvePath(string $path): ?string
    {
        $absolute = realpath($this->docsPath . DIRECTORY_SEPARATOR . $path);

        if (! $absolute) {
            return null;
        }

        $base = realpath($this->docsPath);
        if ($base === false || ! str_starts_with($absolute, $base)) {
            return null;
        }

        if (pathinfo($absolute, PATHINFO_EXTENSION) !== 'md') {
            return null;
        }

        return $absolute;
    }

    protected function getPages(): array
    {
        $pages = [];

        foreach (File::allFiles($this->docsPath) as $file) {
            $absolute = $file->getRealPath();
            $extension = $file->getExtension();

            if ($extension !== 'md') {
                continue;
            }

            $relative = ltrim(str_replace(realpath($this->docsPath), '', realpath($absolute)), DIRECTORY_SEPARATOR);

            $content = File::get($absolute);
            $pages[] = [
                'path' => $relative,
                'title' => $this->extractTitle($content) ?? 'Untitled',
                'search' => preg_replace('/\s+/', ' ', strip_tags($this->renderMarkdown($content))),
            ];
        }

        usort($pages, static fn ($a, $b) => $a['path'] <=> $b['path']);

        return $pages;
    }

    protected function extractTitle(string $content): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function renderMarkdown(string $content): string
    {
        $converter = new GithubFlavoredMarkdownConverter([
            'allow_unsafe_links' => false,
            'html_input' => 'strip',
        ]);

        return $converter->convert($content)->getContent();
    }
}
