<?php declare(strict_types = 1);

namespace Apixu;

use Apixu\Api\ApiInterface;
use Apixu\Exception\InvalidQueryException;
use Apixu\Response\Conditions;
use Apixu\Response\CurrentWeather;
use Apixu\Response\Forecast\Forecast;
use Apixu\Response\History;
use Apixu\Response\Search;
use Psr\Http\Message\StreamInterface;
use Serializer\SerializerInterface;

class Apixu implements ApixuInterface
{
    /**
     * @var ApiInterface
     */
    private $api;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var string
     */
    private static $historyDateFormat = 'Y-m-d';

    /**
     * @param ApiInterface $api
     * @param SerializerInterface $serializer
     */
    public function __construct(ApiInterface $api, SerializerInterface $serializer)
    {
        $this->api = $api;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function conditions() : Conditions
    {
        $url = sprintf(Config::DOC_WEATHER_CONDITIONS_URL, Config::FORMAT);
        $response = $this->api->call($url);

        return $this->getResponse($response, Conditions::class);
    }

    /**
     * {@inheritdoc}
     */
    public function current(string $query, $lang = 'en') : CurrentWeather
    {
        $this->validateQuery($query);
        $this->validateLang($lang);

        $response = $this->api->call('current', ['q' => $query, 'lang' => $lang]);

        return $this->getResponse($response, CurrentWeather::class);
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $query) : Search
    {
        $this->validateQuery($query);
        $response = $this->api->call('search', ['q' => $query]);

        return $this->getResponse($response, Search::class);
    }

  /**
   * {@inheritdoc}
   */
  public function forecast(string $query, int $days, int $hour = null, $lang = 'en') : Forecast
  {
      $this->validateQuery($query);
      $this->validateLang($lang);

      $params = [
        'q' => $query,
        'days' => $days,
        'lang' => $lang,
      ];

      if ($hour !== null) {
          $params['hour'] = $hour;
      }

      $response = $this->api->call('forecast', $params);

      return $this->getResponse($response, Forecast::class);
  }

    /**
     * {@inheritdoc}
     */
    public function history(string $query, \DateTime $since, \DateTime $until = null, $lang = 'en') : History
    {
        $this->validateQuery($query);
        $this->validateLang($lang);

        $params = [
            'q' => $query,
            'dt' => $since->format(self::$historyDateFormat),
            'lang' => $lang,
        ];
        if ($until !== null) {
            $params['end_dt'] = $until->format(self::$historyDateFormat);
        }

        $response = $this->api->call('history', $params);

        return $this->getResponse($response, History::class);
    }

    /**
     * @param string $query
     * @throws InvalidQueryException
     */
    private function validateQuery(string $query)
    {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidQueryException('Query is missing');
        }

        if (strlen($query) > Config::MAX_QUERY_LENGTH) {
            throw new InvalidQueryException(
                sprintf('Query exceeds maximum length (%d)', Config::MAX_QUERY_LENGTH)
            );
        }
    }

    /**
     * @param string $lang
     *
     * @throws \Apixu\Exception\InvalidQueryException
     */
    private function validateLang(string $lang)
    {
        $lang = trim($lang);

        $availableLang = [
          'ar',
          'bn',
          'bg',
          'zh',
          'zh_tw',
          'cs',
          'da',
          'nl',
          'fi',
          'fr',
          'de',
          'el',
          'hi',
          'hu',
          'it',
          'ja',
          'jv',
          'ko',
          'zh_cmn',
          'mr',
          'pl',
          'pa',
          'ro',
          'ru',
          'sr',
          'si',
          'sk',
          'es',
          'sv',
          'ta',
          'te',
          'tr',
          'uk',
          'ur',
          'vi',
          'zh_wuu',
          'zh_hsn',
          'zh_yue',
          'zu',
        ];
        if (!in_array($lang, $availableLang)) {
            throw new InvalidQueryException('Language not supported');
        }
    }

    /**
     * @param StreamInterface $response
     * @param string $class
     * @return mixed
     */
    private function getResponse(StreamInterface $response, string $class)
    {
        return $this->serializer->unserialize($response->getContents(), $class);
    }
}
