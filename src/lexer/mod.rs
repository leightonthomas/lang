pub mod token;

use std::cmp::max;
use std::collections::HashMap;
use std::hash::Hash;
use token::Token;
use crate::lexer::token::{Keyword, Symbol};
use crate::parser::Failure;

#[derive(Debug, Clone)]
pub struct Span {
    pub start: Location,
    pub end: Location,
}
impl Span {
    /// Create a new [Span] that just covers the last character of the one provided.
    pub fn to_last_char(&self) -> Span {
        Span {
            start: Location {
                col: self.end.col,
                row: self.end.row,
            },
            end: Location {
                col: self.end.col,
                row: self.end.row,
            },
        }
    }

    /// Test-only constructor, we don't want to be arbitrarily setting this elsewhere, it should
    /// be clear you're dealing with locations
    #[cfg(test)]
    pub fn new(ax: u16, ay: u16, bx: u16, by: u16) -> Span {
        Span {
            start: Location {
                col: ax,
                row: ay,
            },
            end: Location {
                col: bx,
                row: by,
            },
        }
    }

    #[cfg(test)]
    pub fn split(&self) -> (u16, u16, u16, u16) {
        (self.start.col.clone(), self.start.row.clone(), self.end.col.clone(), self.end.row.clone())
    }
}

/// Wrapper around [Token] that associates it with a [Span].
#[derive(Debug)]
pub struct SourceToken {
    pub token: Token,
    pub span: Span,
}

/// Standardised way of getting the next token assuming the callback `func` doesn't error.
///
/// Provides proper span handling for sudden end-of-input.
#[allow(clippy::needless_lifetimes)]
pub fn get_next_token<'a, T, F: FnOnce(&'a SourceToken) -> Result<T, Option<&'a SourceToken>>>(
    tokens: &'a Vec<SourceToken>,
    index: usize,
    previous_token: &SourceToken,
    func: F,
    error_message: String,
) -> Result<(T, &'a SourceToken), Failure> {
    tokens
        .get(index)
        .ok_or(None)
        .and_then(|next_token| func(next_token).map(|t| (t, next_token)))
        .map_err(|err| Failure {
            message: error_message,
            span: err.map_or(
                previous_token.span.to_last_char(),
                |problematic_token| problematic_token.span.clone(),
            ),
        })
}

pub fn token_is(expected: &Token, tokens: &Vec<SourceToken>, index: usize) -> bool {
    match &tokens.get(index) {
        Some(found) => expected.eq(&found.token),
        None => false,
    }
}

#[derive(Clone, Debug)]
pub struct Location {
    /// The column in the source, starting from 1
    col: u16,
    /// The row in the source, starting from 1
    row: u16,
}

#[derive(Clone, Debug)]
pub struct Position {
    /// The current index into the source code string, starting from 0
    idx: u32,
    /// The column that has been parsed up-to, starting from 1
    col: u16,
    /// The row that has been parsed up-to, starting from 1
    row: u16,
}
impl Position {
    fn span(&self) -> Location {
        Location {
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
    /// Code blocks that were identified during lexing, indexed by their start position in the
    /// tokens vector for easier lookup later.
    code_blocks: HashMap<usize, CodeBlock>,
}

/// A code block is a section of code that's surrounded by braces. Storing a reference to the start
/// and end of the block allows us to more easily parse it later, and know where to stop.
#[derive(Debug)]
pub struct CodeBlock {
    /// The index of the opening brace symbol token in [Token] vector output of the lexer.
    pub start: usize,
    /// The index of the closing brace symbol token in [Token] vector output of the lexer.
    pub end: usize,
}

/// Turn the source code into parsable [Token]s
pub fn lex<T: ?Sized + AsRef<[u8]>>(source: &T) -> LexResult {
    match Lexer::new(source).parse() {
        (Some(msg), _, position, code_blocks) => {
            println!("Code blocks: {:#?}", code_blocks);

            Err((position, msg))
        },
        (None, tokens, _, code_blocks) => Ok((tokens, code_blocks)),
    }
}

type LexResult = Result<(Vec<SourceToken>, HashMap<usize, CodeBlock>), (Position, String)>;
impl Lexer<'_> {
    fn new<T: ?Sized + AsRef<[u8]>>(source: &T) -> Lexer {
        Lexer {
            source: source.as_ref(),
            lexeme: vec![],
            tokens: vec![],
            position: Position { idx: 0, row: 1, col: 1 },
            code_blocks: HashMap::new(),
        }
    }

    fn parse(mut self) -> (Option<String>, Vec<SourceToken>, Position, HashMap<usize, CodeBlock>) {
        // stores the start position (in the output source token vec) of the code blocks as a stack
        let mut code_block_stack: Vec<usize> = vec![];

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
                if &symbol == &Symbol::BraceOpen {
                    code_block_stack.push(self.tokens.len());
                } else if &symbol == &Symbol::BraceClose {
                    if let Some(start) = code_block_stack.pop() {
                        self.code_blocks.insert(start, CodeBlock { start, end: self.tokens.len() });
                    } else {
                        return (Some("Unmatched closing brace".into()), self.tokens, self.position, self.code_blocks);
                    }
                }

                self.skip(1);
                self.add_token(Token::Symbol(symbol), start);

                continue;
            }

            return (Some("Unrecognised input".into()), self.tokens, self.position, self.code_blocks);
        }

        // if there's any code blocks open then there's no point continuing since it's invalid
        if let Some(last_code_block) = code_block_stack.pop() {
            let last_span = self.tokens[last_code_block].span.end.clone();

            return (
                Some("Unclosed code block.".into()),
                self.tokens,
                Position {
                    idx: self.position.idx.clone(),
                    col: last_span.col.clone(),
                    row: last_span.row.clone(),
                },
                self.code_blocks,
            );
        }

        return (None, self.tokens, self.position, self.code_blocks);
    }

    /// Convert the lexeme to a string, since it's a Vec<char> internally
    fn lexeme_to_str(&self) -> String {
        self.lexeme.iter().collect()
    }

    fn add_token(&mut self, token: Token, start: Location) {
        // adjust this so it's one behind, by this point we'll have skipped the last token
        // so technically we'll be pointing at the next thing which is invalid
        let mut current_pos = self.position.span();
        current_pos.col -= 1;

        self.tokens.push(
            SourceToken {
                token,
                span: Span {
                    start,
                    end: current_pos,
                },
            },
        );
    }

    /// View the current character in the source
    fn peek(&self) -> Option<&u8> {
        self.source.get(self.position.idx as usize)
    }

    /// View the current character + `amount` in the source
    fn peek_next(&self, amount: u8) -> Option<&u8> {
        self.source.get((self.position.idx + (amount as u32)) as usize)
    }

    /// Move along the source code from current position, taking all characters that match the
    /// predicate. Once a character does not match, stop.
    ///
    /// Characters will be appended to the lexeme.
    fn take_while<F: Fn(&u8) -> bool>(&mut self, predicate: F) {
        let source_iter = self.source[(self.position.idx as usize)..]
            .iter()
            .take_while(|c| predicate(*c))
        ;

        self.advance(source_iter, true);
    }

    fn skip_all_whitespace(&mut self) {
        let source_iter = self.source[(self.position.idx as usize)..]
            .iter()
            .take_while(|c| c.is_ascii_whitespace())
        ;

        self.advance(source_iter, false);
    }

    /// Move along the source code without taking any characters and placing them into the lexeme
    ///
    /// * `amount` - The number of characters to skip
    fn skip(&mut self, amount: u8) {
        let source_iter = self.source[(self.position.idx as usize)..]
            .iter()
            .take(amount as usize)
        ;

        self.advance(source_iter, false);
    }

    /// Move along the source code (via the the provided iterator) and amend internal position,
    /// optionally taking the characters found.
    ///
    /// * `iter` - The iterator of the source code to advance over
    /// * `take` - Whether to take (append items to lexeme)
    fn advance<'a, I: Iterator<Item = &'a u8>>(&mut self, iter: I, take: bool) {
        let mut additional_rows = 0;
        let mut new_col = self.position.col.clone();

        let mut took = iter
            .map(|c| {
                match c {
                    b'\n' => {
                        additional_rows += 1;
                        new_col = 1;
                    },
                    _ => new_col += 1,
                }

                return c.clone() as char;
            })
            .collect::<Vec<char>>()
        ;

        self.position.row += additional_rows;
        self.position.col = new_col;
        self.position.idx += took.len() as u32;

        if take {
            self.lexeme.append(&mut took);
        }
    }
}
