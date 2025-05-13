<?php

declare(strict_types=1);

namespace OCA\NotMiro\Controller;

use OCA\NotMiro\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class MindmapController extends Controller {
	private IRootFolder $rootFolder;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		IRootFolder $rootFolder,
		IUserSession $userSession,
		LoggerInterface $logger
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Save a mindmap JSON to the user's storage
	 *
	 * @param string $filename The name of the file to save
	 * @param string $content The JSON content of the mindmap
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN, array{status: string, message?: string}, array{}>
	 */
	#[NoAdminRequired]
	public function save(string $filename, string $content): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'User not logged in'], Http::STATUS_FORBIDDEN);
		}

		$userId = $user->getUID();
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			
			// Create NotMiro directory if it doesn't exist
			if (!$userFolder->nodeExists('NotMiro')) {
				$userFolder->newFolder('NotMiro');
			}
			
			$notMiroFolder = $userFolder->get('NotMiro');
			
			// Ensure filename has .mindmap extension
			if (!str_ends_with($filename, '.mindmap')) {
				$filename .= '.mindmap';
			}

			// Create or update the file
			if ($notMiroFolder->nodeExists($filename)) {
				$file = $notMiroFolder->get($filename);
				$file->putContent($content);
			} else {
				$file = $notMiroFolder->newFile($filename);
				$file->putContent($content);
			}

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			$this->logger->error('Error saving mindmap: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return new DataResponse(['status' => 'error', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Load a mindmap from the user's storage
	 *
	 * @param string $filename The name of the file to load
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array{status: string, content?: string, message?: string}, array{}>
	 */
	#[NoAdminRequired]
	public function load(string $filename): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'User not logged in'], Http::STATUS_FORBIDDEN);
		}

		$userId = $user->getUID();
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			
			// Ensure NotMiro directory exists
			if (!$userFolder->nodeExists('NotMiro')) {
				return new DataResponse(['status' => 'error', 'message' => 'No mindmaps found'], Http::STATUS_NOT_FOUND);
			}
			
			$notMiroFolder = $userFolder->get('NotMiro');
			
			// Ensure filename has .mindmap extension
			if (!str_ends_with($filename, '.mindmap')) {
				$filename .= '.mindmap';
			}

			// Get the file
			if (!$notMiroFolder->nodeExists($filename)) {
				return new DataResponse(['status' => 'error', 'message' => 'Mindmap not found'], Http::STATUS_NOT_FOUND);
			}

			$file = $notMiroFolder->get($filename);
			$content = $file->getContent();

			return new DataResponse(['status' => 'success', 'content' => $content]);
		} catch (\Exception $e) {
			$this->logger->error('Error loading mindmap: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return new DataResponse(['status' => 'error', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * List all available mindmaps
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN, array{status: string, files?: array<array{name: string, mtime: int}>, message?: string}, array{}>
	 */
	#[NoAdminRequired]
	public function list(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'User not logged in'], Http::STATUS_FORBIDDEN);
		}

		$userId = $user->getUID();
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			
			// Create NotMiro directory if it doesn't exist
			if (!$userFolder->nodeExists('NotMiro')) {
				$userFolder->newFolder('NotMiro');
				return new DataResponse(['status' => 'success', 'files' => []]);
			}
			
			$notMiroFolder = $userFolder->get('NotMiro');
			$files = $notMiroFolder->getDirectoryListing();

			$mindmapFiles = [];
			foreach ($files as $file) {
				if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE && str_ends_with($file->getName(), '.mindmap')) {
					$mindmapFiles[] = [
						'name' => $file->getName(),
						'mtime' => $file->getMTime()
					];
				}
			}

			return new DataResponse(['status' => 'success', 'files' => $mindmapFiles]);
		} catch (\Exception $e) {
			$this->logger->error('Error listing mindmaps: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return new DataResponse(['status' => 'error', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a mindmap from the user's storage
	 *
	 * @param string $filename The name of the file to delete
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array{status: string, message?: string}, array{}>
	 */
	#[NoAdminRequired]
	public function delete(string $filename): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'User not logged in'], Http::STATUS_FORBIDDEN);
		}

		$userId = $user->getUID();
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			
			// Ensure NotMiro directory exists
			if (!$userFolder->nodeExists('NotMiro')) {
				return new DataResponse(['status' => 'error', 'message' => 'No mindmaps found'], Http::STATUS_NOT_FOUND);
			}
			
			$notMiroFolder = $userFolder->get('NotMiro');
			
			// Ensure filename has .mindmap extension
			if (!str_ends_with($filename, '.mindmap')) {
				$filename .= '.mindmap';
			}

			// Check if file exists
			if (!$notMiroFolder->nodeExists($filename)) {
				return new DataResponse(['status' => 'error', 'message' => 'Mindmap not found'], Http::STATUS_NOT_FOUND);
			}

			// Delete the file
			$file = $notMiroFolder->get($filename);
			$file->delete();

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting mindmap: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return new DataResponse(['status' => 'error', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
} 