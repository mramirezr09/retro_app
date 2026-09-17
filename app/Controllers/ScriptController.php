<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Script;

class ScriptController extends Controller
{
    public function index(): void
    {
        $model = new Script();
        $model->seedFromFile(storage_path('scripts/extraer_foro.js'));

        $this->render('scripts/index', [
            'title'   => 'Scripts',
            'scripts' => $model->activeAll(),
        ]);
    }

    public function create(): void
    {
        $this->render('scripts/form', [
            'title'  => 'Nuevo script',
            'script' => null,
        ]);
    }

    public function edit(string $id): void
    {
        $script = (new Script())->findActive((int) $id);
        if (!$script) {
            $this->flash('error', 'Script no encontrado.');
            $this->redirect(url('/scripts'));
        }
        $this->render('scripts/form', [
            'title'  => 'Editar script',
            'script' => $script,
        ]);
    }

    public function store(): void
    {
        $this->requireCsrf();
        $nombre = $this->request->string('nombre');
        $contenido = $this->contenido();

        if ($nombre === '' || trim($contenido) === '') {
            $this->flash('error', 'El nombre y el contenido son obligatorios.');
            $this->redirect(url('/scripts/create'));
        }

        (new Script())->create([
            'nombre'      => $nombre,
            'descripcion' => $this->request->string('descripcion'),
            'contenido'   => $contenido,
        ]);

        $this->flash('success', 'Script creado correctamente.');
        $this->redirect(url('/scripts'));
    }

    public function update(string $id): void
    {
        $this->requireCsrf();
        $model = new Script();
        $script = $model->findActive((int) $id);
        if (!$script) {
            $this->flash('error', 'Script no encontrado.');
            $this->redirect(url('/scripts'));
        }

        $nombre = $this->request->string('nombre');
        $contenido = $this->contenido();
        if ($nombre === '' || trim($contenido) === '') {
            $this->flash('error', 'El nombre y el contenido son obligatorios.');
            $this->redirect(url('/scripts/' . (int) $id . '/edit'));
        }

        $model->update((int) $id, [
            'nombre'      => $nombre,
            'descripcion' => $this->request->string('descripcion'),
            'contenido'   => $contenido,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->flash('success', 'Script actualizado.');
        $this->redirect(url('/scripts'));
    }

    public function destroy(string $id): void
    {
        $this->requireCsrf();
        (new Script())->softDelete((int) $id);
        $this->flash('success', 'Script eliminado.');
        $this->redirect(url('/scripts'));
    }

    private function contenido(): string
    {
        $value = $this->request->input('contenido', '');
        return is_string($value) ? $value : '';
    }
}
