<?php
interface tokenizeri
{
	public function tokenize(string $expression, array $functionNames = []): array;
}
interface Tokens
{
	const PLUS  = '+';
	const MINUS = '-';
	const MULT  = '*';
	const DIV   = '/';
	const POW   = '^';
	const MOD   = '%';

	const ARG_SEPARATOR = ',';
	const FLOAT_POINT   = '.';

	const PAREN_LEFT    = '(';
	const PAREN_RIGHT   = ')';

	const OPERATORS     = [Tokens::PLUS, Tokens::MINUS, Tokens::MULT, Tokens::DIV, Tokens::POW, Tokens::MOD];
	const PARENTHESES   = [Tokens::PAREN_LEFT, Tokens::PAREN_RIGHT];
}
class tokenizer implements tokenizeri
{
	public function tokenize(string $expression, array $functionNames = []): array
	{
		$exprLength = strlen($expression);

		$tokens = [];
		$numberBuffer = '';
		for($i = 0; $i < $exprLength; $i++) {
			if($expression[$i] === Tokens::MINUS
				&& ($i === 0 || $expression[$i - 1] === Tokens::PAREN_LEFT || $expression[$i - 1] === Tokens::POW
					|| $expression[$i - 1] === Tokens::ARG_SEPARATOR)) {
				$numberBuffer .= $expression[$i];
			}
			else if(ctype_digit($expression[$i]) || $expression[$i] === Tokens::FLOAT_POINT) {
				$numberBuffer .= $expression[$i];
			}
			else if(!ctype_digit($expression[$i]) && $expression[$i] !== Tokens::FLOAT_POINT && strlen($numberBuffer) > 0) {
				if(!is_numeric($numberBuffer)) {
					throw new \InvalidArgumentException('Invalid float number detected (more than 1 float point?)');
				}
				$tokens[] = $numberBuffer;
				$numberBuffer   = '';
				$i--;
			}
			else if(in_array($expression[$i], Tokens::PARENTHESES)) {
				if($tokens && $expression[$i] === Tokens::PAREN_LEFT &&
					(is_numeric($tokens[count($tokens) - 1]) || in_array($tokens[count($tokens) - 1], Tokens::PARENTHESES))) {
					$tokens[] = Tokens::MULT;
				}
				$tokens[] = $expression[$i];
			}
			else if(in_array($expression[$i], Tokens::OPERATORS)) {
				if($i + 1 < $exprLength && $expression[$i] !== Tokens::POW
					&& in_array($expression[$i + 1], Tokens::OPERATORS)) {
					throw new \InvalidArgumentException('Invalid expression');
				}
				$tokens[] = $expression[$i];
			}
			else if($expression[$i] === Tokens::ARG_SEPARATOR) {
				$tokens[] = $expression[$i];
			}
			else if(count($functionNames) > 0) {
				foreach($functionNames as $functionName) {
					$nameLength = strlen($functionName);
					if($i + $nameLength < $exprLength
						&& substr($expression, $i, $nameLength) === $functionName) {
						if($tokens && is_numeric($tokens[count($tokens) - 1])) {
							$tokens[] = Tokens::MULT;
						}
						$tokens[] = $functionName;
						$i = $i + $nameLength - 1;
					}
				}
			}
			else {
				throw new \InvalidArgumentException("Invalid token occurred ({$expression[$i]})");
			}
		}
		if(strlen($numberBuffer) > 0) {
			if(!is_numeric($numberBuffer)) {
				throw new \InvalidArgumentException('Invalid float number detected (more than 1 float point?)');
			}
			$tokens[] = $numberBuffer;
		}
		return $tokens;
	}
}
class calc {
	private $functions = [];
	private $tokenizer;
	public static function create() {
		return new self(new tokenizer());
	}
	public function __construct(tokenizeri $tokenizer) {
		$this->tokenizer = $tokenizer;

		$this->addFunc('sqrt', function($x) { return sqrt($x); });
		$this->addFunc('log', function($base, $arg) { return log($arg, $base); });
	}
	public function addFunc(string $name, callable $function) {
		$name = strtolower(trim($name));
		if(!ctype_alpha(str_replace('_', '', $name))) {
			throw new \InvalidArgumentException('Only letters and underscore are allowed for a name of a function');
		}
		if(array_key_exists($name, $this->functions)) {
			throw new \Exception(sprintf('Function %s exists', $name));
		}
		$reflection = new \ReflectionFunction($function);
		$paramsCount = $reflection->getNumberOfRequiredParameters();
		$this->functions[$name] = [
			'func'        => $function,
			'paramsCount' => $paramsCount,
		];
	}
	public function replaceFunc(string $name, callable $function) {
		$this->removeFunc($name);
		$this->addFunc($name, $function);
	}
	public function removeFunc(string $name) {
		if(!array_key_exists($name, $this->functions)) {
			return;
		}
		unset($this->functions[$name]);
	}
	private function getReversePolishNotation(array $tokens) {
		$queue = new \SplQueue();
		$stack = new \SplStack();

		$tokensCount = count($tokens);
		for($i = 0; $i < $tokensCount; $i++) {
			if(is_numeric($tokens[$i])) {
				$queue->enqueue($tokens[$i] + 0);
			}
			else if(array_key_exists($tokens[$i], $this->functions)) {
				$stack->push($tokens[$i]);
			}
			else if($tokens[$i] === Tokens::ARG_SEPARATOR) {
				if(substr_count($stack->serialize(), Tokens::PAREN_LEFT) === 0) {
					throw new \InvalidArgumentException('Parenthesis are misplaced');
				}
				while($stack->top() != Tokens::PAREN_LEFT) {
					$queue->enqueue($stack->pop());
				}
			}
			else if(in_array($tokens[$i], Tokens::OPERATORS)) {
				while($stack->count() > 0 && in_array($stack->top(), Tokens::OPERATORS)
					&& (($this->isOperatorLeftAssociative($tokens[$i])
						&& $this->getOperatorPrecedence($tokens[$i]) === $this->getOperatorPrecedence($stack->top()))
					|| ($this->getOperatorPrecedence($tokens[$i]) < $this->getOperatorPrecedence($stack->top())))) {
					$queue->enqueue($stack->pop());
				}
				$stack->push($tokens[$i]);
			}
			else if($tokens[$i] === Tokens::PAREN_LEFT) {
				$stack->push(Tokens::PAREN_LEFT);
			}
			else if($tokens[$i] === Tokens::PAREN_RIGHT) {
				if(substr_count($stack->serialize(), Tokens::PAREN_LEFT) === 0) {
					throw new \InvalidArgumentException('Parenthesis are misplaced');
				}
				while($stack->top() != Tokens::PAREN_LEFT) {
					$queue->enqueue($stack->pop());
				}
				$stack->pop();

				if($stack->count() > 0 && array_key_exists($stack->top(), $this->functions)) {
					$queue->enqueue($stack->pop());
				}
			}
		}
		while($stack->count() > 0) {
			$queue->enqueue($stack->pop());
		}
		return $queue;
	}
	private function calcFromRPN(\SplQueue $queue) {
		$stack = new \SplStack();

		while($queue->count() > 0) {
			$currentToken = $queue->dequeue();
			if(is_numeric($currentToken)) {
				$stack->push($currentToken);
			}
			else {
				if(in_array($currentToken, Tokens::OPERATORS)) {
					if($stack->count() < 2) {
						throw new \InvalidArgumentException('Invalid expression');
					}
					$stack->push($this->execOperator($currentToken, $stack->pop(), $stack->pop()));
				}
				else if(array_key_exists($currentToken, $this->functions)) {
					if($stack->count() < $this->functions[$currentToken]['paramsCount']) {
						throw new \InvalidArgumentException('Invalid expression');
					}
					$params = [];
					for($i = 0; $i < $this->functions[$currentToken]['paramsCount']; $i++) {
						$params[] = $stack->pop();
					}
					$stack->push($this->execFunc($currentToken, $params));
				}
			}
		}
		if($stack->count() === 1) {
			return $stack->pop();
		}
		throw new \InvalidArgumentException('Invalid expression');
	}
	public function calc(string $expression) {
		$tokens = $this->tokenizer->tokenize($expression, array_keys($this->functions));
		$rpn    = $this->getReversePolishNotation($tokens);

		$result = $this->calcFromRPN($rpn);

		return $result;
	}
	private function isOperatorLeftAssociative($operator) {
		if(!in_array($operator, Tokens::OPERATORS)) {
			throw new \InvalidArgumentException("Cannot check association of $operator operator");
		}
		if($operator === Tokens::POW)
			return false;

		return true;
	}
	private function getOperatorPrecedence($operator) {
		if(!in_array($operator, Tokens::OPERATORS)) {
			throw new \InvalidArgumentException("Cannot check precedence of $operator operator");
		}
		if($operator === Tokens::POW) {
			return 6;
		}
		else if($operator === Tokens::MULT || $operator === Tokens::DIV) {
			return 4;
		}
		else if($operator === Tokens::MOD) {
			return 2;
		}
		return 1;
	}
	private function execOperator($operator, $a, $b) {
		if($operator === Tokens::PLUS) {
			return $a + $b;
		}
		else if($operator === Tokens::MINUS) {
			return $b - $a;
		}
		else if($operator === Tokens::MOD) {
			return $b % $a;
		}
		else if($operator === Tokens::MULT) {
			return $a * $b;
		}
		else if($operator === Tokens::DIV) {
			if($a === 0) {
				throw new \InvalidArgumentException('Division by zero occured');
			}
			return $b / $a;
		}
		else if($operator === Tokens::POW) {
			return pow($b, $a);
		}
		throw new \InvalidArgumentException('Unknown operator provided');
	}
	private function execFunc($functionName, $params) {
		return call_user_func_array($this->functions[$functionName]['func'], array_reverse($params));
	}
}
