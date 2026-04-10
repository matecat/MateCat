<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Files\FileDao;
use Model\Files\FilesMetadataMarshaller;
use Model\Files\MetadataDao as FilesMetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataMarshaller;

class ContextUrlController extends KleinController
{
    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function setForProject(): void
    {
        $idProject = filter_var($this->request->param('id_project'), FILTER_VALIDATE_INT);
        $contextUrl = trim((string)$this->request->param('context_url'));

        if (empty($idProject)) {
            throw new InvalidArgumentException('Missing or invalid id_project', 400);
        }

        if (empty($contextUrl)) {
            throw new InvalidArgumentException('Missing or empty context_url', 400);
        }

        $dao = new ProjectsMetadataDao();
        $dao->set($idProject, ProjectsMetadataMarshaller::CONTEXT_URL->value, $contextUrl);

        $this->response->json([
            'level'       => 'project',
            'id_project'  => (int)$idProject,
            'context_url' => $contextUrl,
        ]);
    }

    /**
     * @throws Exception
     */
    public function setForFile(): void
    {
        $idProject = filter_var($this->request->param('id_project'), FILTER_VALIDATE_INT);
        $idFile = filter_var($this->request->param('id_file'), FILTER_VALIDATE_INT);
        $contextUrl = trim((string)$this->request->param('context_url'));

        if (empty($idFile)) {
            throw new InvalidArgumentException('Missing or invalid id_file', 400);
        }

        if (empty($contextUrl)) {
            throw new InvalidArgumentException('Missing or empty context_url', 400);
        }

        if (empty($idProject)) {
            $file = FileDao::getById($idFile);
            if (!$file) {
                throw new InvalidArgumentException('File not found for id_file: ' . $idFile, 404);
            }
            $idProject = $file->id_project;
        }

        $dao = new FilesMetadataDao();
        $existing = $dao->get($idProject, $idFile, FilesMetadataMarshaller::CONTEXT_URL->value);
        if ($existing) {
            $dao->update($idProject, $idFile, FilesMetadataMarshaller::CONTEXT_URL->value, $contextUrl);
        } else {
            $dao->insert($idProject, $idFile, FilesMetadataMarshaller::CONTEXT_URL->value, $contextUrl);
        }

        $this->response->json([
            'level'       => 'file',
            'id_project'  => (int)$idProject,
            'id_file'     => (int)$idFile,
            'context_url' => $contextUrl,
        ]);
    }

    /**
     * @throws Exception
     */
    public function setForSegment(): void
    {
        $idSegment = filter_var($this->request->param('id_segment'), FILTER_VALIDATE_INT);
        $contextUrl = trim((string)$this->request->param('context_url'));

        if (empty($idSegment)) {
            throw new InvalidArgumentException('Missing or invalid id_segment', 400);
        }

        if (empty($contextUrl)) {
            throw new InvalidArgumentException('Missing or empty context_url', 400);
        }

        $marshalled = SegmentMetadataMarshaller::CONTEXT_URL->marshall($contextUrl);
        if ($marshalled === null) {
            throw new InvalidArgumentException('Invalid context_url value', 400);
        }

        SegmentMetadataDao::upsert(
            $idSegment,
            SegmentMetadataMarshaller::CONTEXT_URL->value,
            $marshalled
        );

        $this->response->json([
            'level'      => 'segment',
            'id_segment' => (int)$idSegment,
            'context_url' => $contextUrl,
        ]);
    }
}
