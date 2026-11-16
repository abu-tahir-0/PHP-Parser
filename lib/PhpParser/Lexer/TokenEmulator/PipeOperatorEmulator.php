<?php declare(strict_types=1);

namespace PhpParser\Lexer\TokenEmulator;

use PhpParser\Lexer\TokenEmulator\TokenEmulator;
use PhpParser\PhpVersion;
use PhpParser\Token;

class PipeOperatorEmulator extends TokenEmulator {
    public function getPhpVersion(): PhpVersion {
        return PhpVersion::fromComponents(8, 5);
    }

    public function isEmulationNeeded(string $code): bool {
        return \strpos($code, '|>') !== false;
    }

    public function emulate(string $code, array $tokens): array {
        for ($i = 0, $c = count($tokens); $i < $c; ++$i) {
            $token = $tokens[$i];
            if ($token->text === '|' && isset($tokens[$i + 1]) && $tokens[$i + 1]->text === '>') {
                array_splice($tokens, $i, 2, [
                    new Token(\T_PIPE, '|>', $token->line, $token->pos),
                ]);
                $c--;
            }
        }
        return $tokens;
    }

    public function reverseEmulate(string $code, array $tokens): array {
        for ($i = 0, $c = count($tokens); $i < $c; ++$i) {
            $token = $tokens[$i];
            if ($token->id !== \T_PIPE) {
                continue;
            }

            // The host lexer splits "|>=", "|>>" and "|>>=" as T_PIPE followed by "=", ">" or ">=".
            // Reconstruct the tokens the target version would produce for these.
            $numTokens = 1;
            $nextToken = new Token(\ord('>'), '>', $token->line, $token->pos + 1);
            if (isset($tokens[$i + 1]) && $tokens[$i + 1]->pos === $token->pos + 2) {
                $next = $tokens[$i + 1];
                if ($next->text === '=') {
                    $nextToken = new Token(\T_IS_GREATER_OR_EQUAL, '>=', $token->line, $token->pos + 1);
                    $numTokens = 2;
                } elseif ($next->text === '>') {
                    $nextToken = new Token(\T_SR, '>>', $token->line, $token->pos + 1);
                    $numTokens = 2;
                } elseif ($next->id === \T_IS_GREATER_OR_EQUAL) {
                    $nextToken = new Token(\T_SR_EQUAL, '>>=', $token->line, $token->pos + 1);
                    $numTokens = 2;
                }
            }

            array_splice($tokens, $i, $numTokens, [
                new Token(\ord('|'), '|', $token->line, $token->pos),
                $nextToken,
            ]);
            $i++;
            $c += 2 - $numTokens;
        }
        return $tokens;
    }
}
