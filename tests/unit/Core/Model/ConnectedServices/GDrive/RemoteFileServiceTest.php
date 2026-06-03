<?php

namespace Matecat\Core\Model\ConnectedServices\GDrive;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive_Resource_Files;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\GDrive\RemoteFileService;
use Model\RemoteFiles\RemoteFileStruct;
use PHPUnit\Framework\Attributes\Test;

class RemoteFileServiceTest extends AbstractTest
{
    private function createDriveServiceMock(): Google_Service_Drive
    {
        $mock = $this->getStubBuilder(Google_Service_Drive::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getClient'])
            ->getStub();

        $mock->files = $this->createStub(Google_Service_Drive_Resource_Files::class);

        return $mock;
    }

    private function createClientStub(): Google_Client
    {
        return $this->createStub(Google_Client::class);
    }

    #[Test]
    public function officeMimeFromGoogleMapsGoogleDocsToDocx(): void
    {
        $this->assertSame(RemoteFileService::MIME_DOCX,
            RemoteFileService::officeMimeFromGoogle(RemoteFileService::MIME_GOOGLE_DOCS));
    }

    #[Test]
    public function officeMimeFromGoogleMapsGoogleSlidesToPptx(): void
    {
        $this->assertSame(RemoteFileService::MIME_PPTX,
            RemoteFileService::officeMimeFromGoogle(RemoteFileService::MIME_GOOGLE_SLIDES));
    }

    #[Test]
    public function officeMimeFromGoogleMapsGoogleSheetsToXlsx(): void
    {
        $this->assertSame(RemoteFileService::MIME_XLSX,
            RemoteFileService::officeMimeFromGoogle(RemoteFileService::MIME_GOOGLE_SHEETS));
    }

    #[Test]
    public function officeMimeFromGoogleReturnsOriginalForUnknownMime(): void
    {
        $this->assertSame('application/unknown',
            RemoteFileService::officeMimeFromGoogle('application/unknown'));
    }

    #[Test]
    public function officeExtensionFromMimeReturnsDocxForGoogleDocs(): void
    {
        $this->assertSame('.docx',
            RemoteFileService::officeExtensionFromMime(RemoteFileService::MIME_GOOGLE_DOCS));
    }

    #[Test]
    public function officeExtensionFromMimeReturnsPptxForGoogleSlides(): void
    {
        $this->assertSame('.pptx',
            RemoteFileService::officeExtensionFromMime(RemoteFileService::MIME_GOOGLE_SLIDES));
    }

    #[Test]
    public function officeExtensionFromMimeReturnsXlsxForGoogleSheets(): void
    {
        $this->assertSame('.xlsx',
            RemoteFileService::officeExtensionFromMime(RemoteFileService::MIME_GOOGLE_SHEETS));
    }

    #[Test]
    public function officeExtensionFromMimeReturnsNullForUnknownMime(): void
    {
        $this->assertNull(
            RemoteFileService::officeExtensionFromMime('application/unknown'));
    }

    #[Test]
    public function getServiceEncodesArrayToken(): void
    {
        $client = $this->getMockBuilder(Google_Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects($this->once())
            ->method('setAccessToken')
            ->with($this->isString());

        $result = RemoteFileService::getService(['access_token' => 'abc'], $client);
        $this->assertInstanceOf(Google_Service_Drive::class, $result);
    }

    #[Test]
    public function getServicePassesStringTokenDirectly(): void
    {
        $client = $this->getMockBuilder(Google_Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects($this->once())
            ->method('setAccessToken')
            ->with('raw');

        $result = RemoteFileService::getService('raw', $client);
        $this->assertInstanceOf(Google_Service_Drive::class, $result);
    }

    #[Test]
    public function constructorUsesInjectedDriveService(): void
    {
        $driveService = $this->createDriveServiceMock();
        $driveService->files = $this->createStub(Google_Service_Drive_Resource_Files::class);


        $service = new RemoteFileService('token', $this->createClientStub(), $driveService);

        $ref = new \ReflectionClass($service);
        $prop = $ref->getProperty('gdriveService');
        $this->assertSame($driveService, $prop->getValue($service));
    }

    #[Test]
    public function getFileLinkReturnsDriveFile(): void
    {
        $driveService = $this->createDriveServiceMock();
        $driveService->files = $this->createMock(Google_Service_Drive_Resource_Files::class);
        $expected = $this->createStub(Google_Service_Drive_DriveFile::class);

        $driveService->files->expects($this->once())
            ->method('get')
            ->with('remote123', ['fields' => 'capabilities, webViewLink'])
            ->willReturn($expected);

        $service = new RemoteFileService('token', $this->createClientStub(), $driveService);
        $this->assertSame($expected, $service->getFileLink('remote123'));
    }

    #[Test]
    public function updateFileReturnsGdriveFileOnSuccess(): void
    {
        $driveService = $this->createDriveServiceMock();
        $gdriveFile = $this->createStub(Google_Service_Drive_DriveFile::class);
        $gdriveFile->method('getCapabilities')->willReturn((object)['canAddMyDriveParent' => false]);
        $gdriveFile->method('getParents')->willReturn([]);
        $gdriveFile->method('getName')->willReturn('f.txt');
        $gdriveFile->method('getDescription')->willReturn('');
        $gdriveFile->method('getKind')->willReturn('drive#file');
        $gdriveFile->method('getMimeType')->willReturn('');
        $gdriveFile->mimeType = 'text/plain';

        $remoteFile = $this->createStub(RemoteFileStruct::class);
        $remoteFile->remote_id = 'remote123';

        $driveService->files->method('get')->willReturn($gdriveFile);
        $driveService->files->method('update');

        $service = new RemoteFileService('token', $this->createClientStub(), $driveService);
        $this->assertSame($gdriveFile, $service->updateFile($remoteFile, 'content'));
    }

    #[Test]
    public function updateFileChecksTokenAndRethrowsOnException(): void
    {
        $client = $this->createClientStub();
        $client->method('isAccessTokenExpired')->willReturn(true);

        $driveService = $this->createDriveServiceMock();
        $driveService->files->method('get')->willThrowException(new \Exception('API error'));
        $driveService->method('getClient')->willReturn($client);

        $remoteFile = $this->createStub(RemoteFileStruct::class);
        $remoteFile->remote_id = 'remote123';

        $service = new RemoteFileService('token', $client, $driveService);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API error');
        $service->updateFile($remoteFile, 'content');
    }

    #[Test]
    public function copyFileReturnsDriveFileOnSuccess(): void
    {
        $driveService = $this->createDriveServiceMock();
        $copied = $this->createStub(Google_Service_Drive_DriveFile::class);

        $driveService->files->method('copy')->willReturn($copied);

        $service = new RemoteFileService('token', $this->createClientStub(), $driveService);
        $this->assertSame($copied, $service->copyFile('origin123', 'Copy'));
    }

    #[Test]
    public function copyFileWrapsExceptionWithErrorMessage(): void
    {
        $driveService = $this->createDriveServiceMock();
        $driveService->files->method('copy')
            ->willThrowException(new \Exception('{"error":{"message":"Denied"}}'));

        $service = new RemoteFileService('token', $this->createClientStub(), $driveService);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Copy File - GDrive Error: Denied');
        $service->copyFile('origin123', 'Copy');
    }

    #[Test]
    public function updateFileWithParentsAddsEnforceSingleParent(): void
    {
        $driveService = $this->createDriveServiceMock();
        $driveService->files = $this->createMock(Google_Service_Drive_Resource_Files::class);
        $gdriveFile = $this->createStub(Google_Service_Drive_DriveFile::class);
        $gdriveFile->method('getCapabilities')->willReturn((object)['canAddMyDriveParent' => true]);
        $gdriveFile->method('getParents')->willReturn(['parent1']);
        $gdriveFile->method('getName')->willReturn('f.txt');
        $gdriveFile->method('getDescription')->willReturn('');
        $gdriveFile->method('getKind')->willReturn('drive#file');
        $gdriveFile->method('getMimeType')->willReturn('');
        $gdriveFile->mimeType = 'text/plain';

        $remoteFile = $this->createStub(RemoteFileStruct::class);
        $remoteFile->remote_id = 'remote123';

        $driveService->files->method('get')->willReturn($gdriveFile);
        $driveService->files->expects($this->once())->method('update')
            ->with('remote123', $this->anything(), $this->callback(function ($opts) {
                return ($opts['enforceSingleParent'] ?? false) === true
                    && ($opts['addParents'] ?? '') === 'parent1';
            }));

        $service = new RemoteFileService('token', $this->createClientStub(), $driveService);
        $service->updateFile($remoteFile, 'content');
    }

    #[Test]
    public function updateFileWithoutParentsDoesNotSetEnforceSingleParent(): void
    {
        $driveService = $this->createDriveServiceMock();
        $driveService->files = $this->createMock(Google_Service_Drive_Resource_Files::class);
        $gdriveFile = $this->createStub(Google_Service_Drive_DriveFile::class);
        $gdriveFile->method('getCapabilities')->willReturn((object)['canAddMyDriveParent' => false]);
        $gdriveFile->method('getParents')->willReturn([]);
        $gdriveFile->method('getName')->willReturn('f.txt');
        $gdriveFile->method('getDescription')->willReturn('');
        $gdriveFile->method('getKind')->willReturn('drive#file');
        $gdriveFile->method('getMimeType')->willReturn('');
        $gdriveFile->mimeType = 'text/plain';

        $remoteFile = $this->createStub(RemoteFileStruct::class);
        $remoteFile->remote_id = 'remote123';

        $driveService->files->method('get')->willReturn($gdriveFile);
        $driveService->files->expects($this->once())->method('update')
            ->with('remote123', $this->anything(), $this->callback(function ($opts) {
                return !isset($opts['enforceSingleParent']);
            }));

        $service = new RemoteFileService('token', $this->createClientStub(), $driveService);
        $service->updateFile($remoteFile, 'content');
    }
}