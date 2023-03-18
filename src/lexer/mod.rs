pub mod token;

use token::Token;
use crate::lexer::token::{Keyword, Symbol};

/// Wrapper around [Token] that associates it with a [Span].
#[derive(Debug)]
pub struct SourceToken {
    token: Token,
    start: Span,
    end: Span,
}

#[derive(Clone, Debug)]
pub struct Span {
    /// The 0-indexed column in the source
    col: u16,
    /// The 0-indexed row in the source
    row: u16,
}

#[derive(Clone, Debug)]
pub struct Position {
    /// The current index into the source code string
    idx: u32,
    /// The 0-indexed column that has been parsed up-to
    col: u16,
    /// The 0-indexed row that has been parsed up-to
    row: u16,
}
impl Position {
    fn span(&self) -> Span {
        Span {
            row: self.row.clone(),
            col: self.col.clone(),
        }
    }
}

pub struct Lexer<'src> {
    source: &'src [u8],
    lexeme: Vec<char>,
    tokens: Vec<SourceToken>,
    position: Position,
}

/// Turn the source code into parsable [Token]s
pub fn lex<T: ?Sized + AsRef<[u8]>>(source: &T) -> LexResult {
    match Lexer::new(source).parse() {
        (Some(msg), _, position) => Err((position, msg)),
        (None, tokens, _) => Ok(tokens),
    }
}

type LexResult = Result<Vec<SourceToken>, (Position, String)>;
impl Lexer<'_> {
    fn new<T: ?Sized + AsRef<[u8]>>(source: &T) -> Lexer {
        Lexer {
            source: source.as_ref(),
            lexeme: vec![],
            tokens: vec![],
            position: Position { idx: 0, row: 0, col: 0 },
        }
    }

    fn parse(mut self) -> (Option<String>, Vec<SourceToken>, Position) {
        loop {
            self.skip_all_whitespace();
            self.lexeme = vec![];

            // idk a nicer way to do this because jetbrains doesn't like let else :(
            let raw_peeked_current = self.peek();
            if raw_peeked_current.is_none() {
                break;
            }

            let start = self.position.span();
            let peeked_current = raw_peeked_current.unwrap();

            if peeked_current.eq(&b';') {
                self.skip(1);
                self.add_token(Token::End, start);

                continue;
            }

            // we have to peek next here, it could be a symbol
            if peeked_current.eq(&b'/') && self.peek_next(1).unwrap_or(&b';').eq(&b'/') {
                self.skip(2);
                self.take_while(|c| ! c.eq(&&b'\n'));

                self.add_token(Token::Comment(self.lexeme_to_str()), start);

                continue;
            }

            // string literal
            if peeked_current.eq(&b'"') {
                self.skip(1);
                self.take_while(|c| ! c.eq(&b'"'));
                self.skip(1);

                self.add_token(Token::StringLiteral(self.lexeme_to_str()), start);

                continue;
            }

            if peeked_current.is_ascii_digit() {
                self.take_while(|c| c.is_ascii_digit());
                self.add_token(
                    // we _should_ be safe to cast this, we've only taken digits in lexeme
                    Token::IntegerLiteral(self.lexeme_to_str().parse::<i64>().unwrap()),
                    start,
                );

                continue;
            }

            // keyword/identifier
            if peeked_current.is_ascii_alphabetic() {
                self.take_while(u8::is_ascii_alphanumeric);

                let full_lexeme = self.lexeme_to_str();
                if let Some(keyword) = Keyword::from_string(&full_lexeme) {
                    self.add_token(Token::Keyword(keyword), start);
                } else {
                    self.add_token(Token::Identifier(full_lexeme), start);
                }

                continue;
            }

            if let Some(symbol) = Symbol::from_char(peeked_current) {
                self.skip(1);
                self.add_token(Token::Symbol(symbol), start);

                continue;
            }

            return (Some("Unrecognised input".into()), self.tokens, self.position);
        }

        return (None, self.tokens, self.position);
    }

    fn lexeme_to_str(&self) -> String {
        self.lexeme.iter().map(|c| *c).collect()
    }

    fn add_token(&mut self, token: Token, start: Span) {
        self.tokens.push(
            SourceToken {
                token,
                start,
                end: self.position.span(),
            },
        );
    }

    fn take_while<F: Fn(&u8) -> bool>(&mut self, predicate: F) {
        let mut additional_rows = 0;
        let mut new_col = self.position.col.clone();

        let mut took = self.source[(self.position.idx as usize)..]
            .iter()
            .take_while(|c| predicate(*c))
            .map(|c| {
                match c {
                    b'\n' => {
                        additional_rows += 1;
                        new_col = 0;
                    },
                    _ => new_col += 1,
                }

                return (*c) as char;
            })
            .collect::<Vec<char>>()
        ;

        self.position.row += additional_rows;
        self.position.col = new_col;
        self.position.idx += took.len() as u32;
        self.lexeme.append(&mut took);
    }

    fn skip_all_whitespace(&mut self) {
        loop {
            match self.peek() {
                None => return,
                Some(c) => if c.is_ascii_whitespace() {
                    self.skip(1);

                    continue;
                } else {
                    return
                }
            }
        }
    }

    /// TODO this can call `take_while` or take_while can call this maybe? they share same code
    /// Move along the source code without taking any characters and placing them into the lexeme
    ///
    /// * `amount` - The number of characters to skip
    fn skip(&mut self, amount: u8) {
        let mut additional_rows = 0;
        let mut new_col = self.position.col.clone();
        let mut actual_amount = 0;

        for i in 0..amount {
            match self.peek_next(i) {
                Some(b'\n') => {
                    additional_rows += 1;
                    new_col = 0;
                },
                Some(_) => new_col += 1,
                None => break,
            }

            actual_amount += 1
        }

        self.position.row += additional_rows;
        self.position.col = new_col;
        self.position.idx += actual_amount as u32;
    }

    /// View the current character in the source
    fn peek(&self) -> Option<&u8> {
        self.source.get(self.position.idx as usize)
    }

    /// View the current character + `amount` in the source
    fn peek_next(&self, amount: u8) -> Option<&u8> {
        self.source.get((self.position.idx + (amount as u32)) as usize)
    }
}
