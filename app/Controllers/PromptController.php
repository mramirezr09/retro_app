<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Prompt;

class PromptController extends Controller
{
    public function index(): void
    {
        $this->render('prompts/index', [
            'title'   => 'Prompts',
            'prompts' => (new Prompt())->activeAll(),
        ]);
    }

    public function create(): void
    {
        $this->render('prompts/form', [
            'title'  => 'Nuevo prompt',
            'prompt' => null,
        ]);
    }

    public function edit(string $id): void
    {
        $prompt = (new Prompt())->findActive((int) $id);
        if (!$prompt) {
            $this->flash('error', 'Prompt no encontrado.');
            $this->redirect(url('/prompts'));
        }
        $this->render('prompts/form', [
            'title'  => 'Editar prompt',
            'prompt' => $prompt,
        ]);
    }

    public function store(): void
    {
        $this->requireCsrf();
        $nombre = $this->request->string('nombre');
        $contenido = $this->request->string('contenido');

        if ($nombre === '' || $contenido === '') {
            $this->flash('error', 'El nombre y el contenido son obligatorios.');
            $this->redirect(url('/prompts/create'));
        }

        (new Prompt())->create([
            'nombre'    => $nombre,
            'materia'   => $this->request->string('materia'),
            'contenido' => $contenido,
        ]);

        $this->flash('success', 'Prompt creado correctamente.');
        $this->redirect(url('/prompts'));
    }

    public function update(string $id): void
    {
        $this->requireCsrf();
        $model = new Prompt();
        $prompt = $model->findActive((int) $id);
        if (!$prompt) {
            $this->flash('error', 'Prompt no encontrado.');
            $this->redirect(url('/prompts'));
        }

        $nombre = $this->request->string('nombre');
        $contenido = $this->request->string('contenido');
        if ($nombre === '' || $contenido === '') {
            $this->flash('error', 'El nombre y el contenido son obligatorios.');
            $this->redirect(url('/prompts/' . (int) $id . '/edit'));
        }

        $model->update((int) $id, [
            'nombre'     => $nombre,
            'materia'    => $this->request->string('materia'),
            'contenido'  => $contenido,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash('success', 'Prompt actualizado.');
        $this->redirect(url('/prompts'));
    }

    public function destroy(string $id): void
    {
        $this->requireCsrf();
        (new Prompt())->softDelete((int) $id);
        $this->flash('success', 'Prompt eliminado.');
        $this->redirect(url('/prompts'));
    }
}
