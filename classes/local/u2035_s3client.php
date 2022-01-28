<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * S3 клиент для отправки артефактов обучения
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\local;

defined('MOODLE_INTERNAL') || die();

use Aws\S3\S3Client as AwsS3Client;
use Aws\Credentials\Credentials as AwsCredentials;
use DateTimeImmutable;
use coding_exception;

class u2035_s3client implements s3client_interface {

    const AWS_API_VERSION = '2006-03-01';
    const AWS_API_REGION = 'eu-central-1';

    /**
     * @var AwsS3Client
     */
    protected $client;

    /**
     * @var string|null
     */
    protected $defaultbucketname = null;

    /**
     * @param AwsS3Client $client
     */
    public function __construct(AwsS3Client $client, $defaultbucketname = null) {
        $this->client = $client;
        if ($defaultbucketname) {
        $this->defaultbucketname = $defaultbucketname;
        }
    }

    /**
     * Вернёт собственный экземпляр
     *
     * @return self
     */
    public static function build() {
        $awsendpoint = get_config('logstore_xapi', 's3_endpoint');
        $awskey      = get_config('logstore_xapi', 'u2035_aws_key');
        $awssecret   = get_config('logstore_xapi', 'u2035_aws_secret');
        $awsbucket   = get_config('logstore_xapi', 's3_bucket');

        foreach (compact('awsendpoint', 'awskey', 'awssecret', 'awsbucket') as $name => $conf) {
            if (false === $conf) {
                $errMsg = 'Не возможно создать клиент s3 сервиса. Не указан параметр настройки';
                throw new coding_exception($errMsg, $name);
            }
        }

        $s3client = new AwsS3Client([
            'credentials' => new AwsCredentials($awskey, $awssecret),
            'endpoint' => $awsendpoint,
            'region' => static::AWS_API_REGION,
            'version' => static::AWS_API_VERSION
        ]);

        return new self($s3client, $awsbucket);
    }

    /**
     * Выполнит загрузку файла в S3
     *
     * @param string $bucketname  бакет в котором нужно разместить объект
     * @param string $key         Ключь объекта (Имя файла)
     * @param mixed  $body        Object data to upload. Can be a
     *                            StreamInterface, PHP stream
     *                            resource, or a string of data to
     *                            upload.
     * @param string $acl         @see https://docs.aws.amazon.com/AmazonS3/latest/userguide/acl-overview.html#permissions Canned ACL
     *
     * @return object объект с ключами etag, url, lastmodified
     */
    public function upload($key, $body, $bucketname = null,  $acl = 'public-read') {
        $bucketname = $bucketname ?? $this->defaultbucketname;
        $response = $this->client->upload($bucketname, $key, $body, $acl);
        $result = [
            'etag' => $response['ETag'],
            'url' => $response['ObjectURL'],
            'lastmodified' => new DateTimeImmutable($response['LastModified']),
        ];

        return $result;
    }
}
