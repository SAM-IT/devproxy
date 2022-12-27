<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$factory = new \React\Datagram\Factory();

function extract_bits(int $value, int $start, int $length = 1)
{
    $mask = (1 << ($length)) - 1;
    return ($value >> $start) & $mask;
}

enum RecordType: int {
    case A = 1;
    case NS = 2;
    case MD = 3;
    case MF  = 4;

    case CNAME  = 5;
    case SOA = 6;
    case MB = 7;
    case MG = 8;
    case MR = 9;

    case NULL = 10;
    case WKS = 11;
    case PTR = 12;
    case HINFO = 13;
    case MINFO = 14;
    case MX = 15;
    case TXT = 16;
    case AAAA = 28;
}

enum QuestionType: int {
    case A = 1;
    case NS = 2;
    case MD = 3;
    case MF  = 4;

    case CNAME  = 5;
    case SOA = 6;
    case MB = 7;
    case MG = 8;
    case MR = 9;

    case NULL = 10;
    case WKS = 11;
    case PTR = 12;
    case HINFO = 13;
    case MINFO = 14;
    case MX = 15;
    case TXT = 16;
    case AAAA = 28;

    case AXFR = 252;
    case MAILB = 253;
    case MAILA = 254;
    case ASTERISK = 255;
}


enum RecordClass: int {
    case IN = 1;
    case CS = 2;
    case CH = 3;
    case HS = 4;
}

enum QuestionClass: int {
    case IN = 1;
    case CS = 2;
    case CH = 3;
    case HS = 4;
    case ASTERISK = 255;
}

class StringWriter {
    public function writeSegments(array $segments): string
    {
        $result = '';
        foreach($segments as $segment) {
            $result .= pack('c', strlen($segment)) . $segment;
        }
        $result .= pack('c', 0);
        return $result;
    }

    public function writeType(QuestionType|RecordType $type): string
    {
        $recordType = RecordType::from($type->value);
        return pack('n', $recordType->value);
    }
    public function writeClass(QuestionClass|RecordClass $class): string
    {
        $recordClass = RecordType::from($class->value);
        return pack('n', $recordClass->value);
    }
    public function writeInt(int $value): string
    {
        if ($value >= 2 ** 16 || $value < 0) {
            throw new InvalidArgumentException();
        }
        $result = pack('n', $value);;
        if (strlen($result) !== 2) {
            die("expected length 2");
        }
        return $result;
    }
}
class StringParser {
    public function __construct(private string $data)
    {

    }

    public function read16BitInt(): int {

        $result = unpack('n', $this->data)[1];
        $this->data = substr($this->data, 2);
        return $result;
    }

    public function readByte(): int {

        $result = unpack('c', $this->data)[1];
        $this->data = substr($this->data, 1);
        return $result;
    }

    public function read16Bits(): BitReader {

        $result = BitReader::for16bit(unpack('n', $this->data)[1]);
        $this->data = substr($this->data, 2);
        return $result;
    }

    /**
     * Reads a string prefixed by a byte that indicates its length.
     * If the prefix byte is 0.
     * @return string
     */
    public function readByteLengthString(): string
    {
        $length = $this->readByte();
        $result = substr($this->data, 0, $length);
        $this->data = substr($this->data, $length);
        return $result;
    }

    /**
     * Reads string segments, stops when an empty segment is read. The empty segment is not part of the result.
     * @return list<string>
     */
    public function readSegments(): array
    {
        $result = [];
        while('' !== $segment = $this->readByteLengthString()) {
            $result[] = $segment;
        }
        return $result;
    }
}
class BitReader {
    public function __construct(private readonly int $value, private readonly int $length)
    {
        if ($value > (2 ** $length) - 1) {
            throw new \InvalidArgumentException('Value too large for length');
        }
    }

    public static function for16bit(int $value): self
    {
        return new self($value, 16);
    }

    public function bool(int $index): bool
    {
        return $this->subInt($index, 1) === 1;
    }

    public function subInt(int $start, int $length): int {
        $mask = (1 << $length) - 1;
        $shifted = $this->value >> ($this->length - $start - $length);
        return $shifted & $mask;
    }
}



$records = [];
$factory->createServer('0.0.0.0:53')->then(function(\React\Datagram\Socket $server) use ($factory, &$records) {
    echo "Listening on: {$server->getLocalAddress()}\n";
    $server->on('message', function(string $message, string $clientAddress, \React\Datagram\Socket $socket) use(&$records) {
        $parser = new StringParser($message);

        $id = $parser->read16BitInt();
        $bits = $parser->read16Bits();
        $qdcount = $parser->read16BitInt();
        $ancount = $parser->read16BitInt();
        $nscount = $parser->read16BitInt();
        $arcount = $parser->read16BitInt();
        $qr = $bits->bool(0) ? 'query' : 'response';
        $opcode = $bits->subInt(1, 4);
        $aa = $bits->bool(5);
        $tc = $bits->bool(6);
        $rd = $bits->bool(7);
        $ra = $bits->bool(8);
        $z = $bits->bool(9);
        $ad = $bits->bool(10);
        $cd = $bits->bool(11);
        $rcode =$bits->subInt(12, 4);


        echo "Qdcount: {$qdcount}\n";
        if ($qdcount > 1) {
            echo "Ignoring due to querycount > 1\n";
        }
        $segments = $parser->readSegments();


        echo "Got query for: " . print_r($segments, true);
        $domain = implode('.', $segments) . '.';
        $answers = $records[$domain] ?? [];
        if (count($answers)> 0) {
            echo "MATCH with local records\n";
            print_r($answers);
        }
        $qtype = QuestionType::from($parser->read16BitInt());
        $qclass = QuestionClass::from($parser->read16BitInt());
        var_dump($qtype, $qclass);

        /**
         * 4.1.1. Header section format

        The header contains the following fields:

        1  1  1  1  1  1
        0  1  2  3  4  5  6  7  8  9  0  1  2  3  4  5
        +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
        |                      ID                       |
        +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
        |QR|   Opcode  |AA|TC|RD|RA|   Z    |   RCODE   |
        +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
        |                    QDCOUNT                    |
        +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
        |                    ANCOUNT                    |
        +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
        |                    NSCOUNT                    |
        +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
        |                    ARCOUNT                    |
        +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
         */
        $writer = new StringWriter();
        $bits = 0b1000010000000000;
        $ancount = count($answers);

        $response = pack('nnnnnn', $id, $bits, 1, $ancount, 0, 0);
        //Question
        $response .= $writer->writeSegments($segments);
        $response .= $writer->writeType($qtype);
        $response .= $writer->writeClass($qclass);
        // RR
//        $response .= $writer->writeSegments($segments);


        foreach($answers as $answer) {
            $response .= "\0";
            $response .= $writer->writeType($qtype);
            $response .= $writer->writeClass($qclass);
            $response .= pack('N', 0);

            $data = match($qtype) {
                // QuestionType::A => pack('c*', 127, 0, 0, 1),
                // QuestionType::MX => $writer->writeInt(10) . $writer->writeSegments(['abc', 'def']),
                // QuestionType::CNAME => $writer->writeSegments(['abc', 'def']),
                QuestionType::TXT => substr($writer->writeSegments([$answer]), 0, -1)
            };
            $response .= $writer->writeInt(strlen($data));
            $response .= $data;
        }
        echo "Sending response data to $clientAddress: $response\n";
        $socket->send($response, $clientAddress);

    });
});

$http = new \React\Http\HttpServer(function(\Psr\Http\Message\ServerRequestInterface $request) use (&$records) {
    try {
        $record = json_decode($request->getBody()->getContents(), true, 3, JSON_THROW_ON_ERROR);

        switch($request->getUri()->getPath()) {
            case '/present':
                echo "Adding record: " . print_r($record, true);
                $records[$record['fqdn']][$record['value']] = $record['value'];
                break;
            case '/cleanup':
                echo "Removing record: " . print_r($record, true);
                unset($records[$record['fqdn']][$record['value']]);
                break;
            default:
                echo "Ignoring unknown endpoint: {$request->getUri()->getPath()}";
        }
    } catch (\Error $e) {
        echo "{$e->getMessage()}\n";
    }
    return React\Http\Message\Response::plaintext(
        "OK\n"
    );
});


$socket = new \React\Socket\SocketServer('0.0.0.0:8082');

echo "Listening for HTTP\n";
$http->listen($socket);
