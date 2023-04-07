use std::collections::HashMap;
use crate::lexer::{get_next_token, SourceToken, Span};
use crate::lexer::token::{Keyword, Symbol, Token};
use crate::parser::{Failure, File};
use crate::parser::statement::{Function, ToTypeReference, Use};

/// Parse a "use" statement, assuming that the "use" keyword itself has already been provided.
///
/// ## Arguments
/// * `file` - the file context that we'll be adding the use statement to
/// * `tokens` - the whole set of tokens for the file
/// * `starting_index` - the index of the use keyword
/// * `starting_token` - the token for the use keyword
///
/// ## Examples of parseable data
/// * `Foo;`
/// * `Foo.Bar.Baz;`
///
/// ## Returns
/// A [Result] containing the new index into the tokens after parsing the use statement, or a
/// failure.
pub fn parse_use(
    file: &mut File,
    tokens: &Vec<SourceToken>,
    starting_index: &usize,
    starting_token: &SourceToken,
) -> Result<usize, Failure> {
    let mut identifiers: Vec<&str> = vec![];
    let mut next_index = starting_index.clone() + 1;
    let mut previous_token: &SourceToken = &starting_token;

    // we'll either run out of input, encounter a bad token, or reach or success case
    loop {
        let (immediate_next, _) = get_next_token(
            tokens,
            next_index,
            previous_token,
            |next_token| match next_token.token {
                Token::Identifier(_) | Token::End => Ok(next_token),
                _ => Err(Some(next_token)),
            },
            "Expected an identifier.".to_owned(),
        )?;

        next_index += 1; // advance past the identifier/end

        match &immediate_next.token {
            Token::End => {
                if identifiers.len() <= 0 {
                    return Err(Failure {
                        message: "At least one identifier must be provided in a use statement.".to_owned(),
                        span: immediate_next.span.clone(),
                    });
                }

                let new_use = Use {
                    identifier: identifiers.as_type_reference().to_owned(),
                    span: Span {
                        start: starting_token.span.start.clone(),
                        // since we did an identifiers check we know this'll be present, otherwise how
                        // did anything get into the identifiers? :clueless:
                        end: previous_token.span.end.clone(),
                    },
                };
                file.uses.insert(identifiers.last().unwrap().to_string(), new_use);

                return Ok(next_index);
            },
            Token::Identifier(identifier) => {
                identifiers.push(&identifier);
            }
            _ => {},
        }

        previous_token = &immediate_next;

        // try the next token, if it's a period then we can ignore it and increment the index again.
        // if it's not a period then we'll let it loop once more and it'll get caught by the
        // identifier/end check at the start
        if let Some(maybe_period_token) = tokens.get(next_index) {
            match maybe_period_token.token {
                Token::Symbol(Symbol::Period) => {
                    previous_token = &maybe_period_token;

                    next_index += 1;
                },
                _ => continue,
            }
        }
    }
}

#[cfg(test)]
mod tests {
    use std::collections::HashMap;
    use crate::lexer::{SourceToken, Span};
    use crate::lexer::token::{Keyword, Symbol, Token};
    use crate::parser::{File};
    use crate::parser::top_level::uses::parse_use;

    #[test]
    fn it_will_not_parse_if_no_next_input() {
        let tokens: Vec<SourceToken> = vec![
            SourceToken {
                token: Token::Keyword(Keyword::Use),
                span: Span::new(1, 1, 3, 1),
            },
        ];
        let mut file = File {
            namespace: "Some.Base.Namespace".to_string(),
            uses: HashMap::new(),
            functions: HashMap::new(),
        };

        match parse_use(&mut file, &tokens, &0, &tokens[0]) {
            Ok(_) => panic!("Should not have parsed correctly"),
            Err(failure) => {
                assert_eq!("Expected an identifier.", failure.message);
                assert_eq!(failure.span.split(), (3, 1, 3, 1));
            },
        }
    }

    #[test]
    fn it_will_not_parse_if_first_input_not_identifier() {
        let second_token_span = Span::new(4, 1, 4, 1);
        let example_invalid_tokens = vec![
            Token::Keyword(Keyword::Use),
            Token::Symbol(Symbol::Equal),
            Token::Comment("hi".to_owned()),
            Token::StringLiteral("hi".to_owned()),
        ];

        for example_token in example_invalid_tokens {
            let tokens: Vec<SourceToken> = vec![
                SourceToken {
                    token: Token::Keyword(Keyword::Use),
                    span: Span::new(1, 1, 3, 1),
                },
                SourceToken {
                    token: example_token,
                    span: second_token_span.clone(),
                },
            ];
            let mut file = File {
                namespace: "Some.Base.Namespace".to_string(),
                uses: HashMap::new(),
                functions: HashMap::new(),
            };

            match parse_use(&mut file, &tokens, &0, &tokens[0]) {
                Ok(_) => panic!("Should not have parsed correctly"),
                Err(failure) => {
                    assert_eq!("Expected an identifier.", failure.message);
                    assert_eq!(failure.span.split(), (4, 1, 4, 1));
                },
            }
        }
    }

    #[test]
    fn it_will_not_parse_if_first_input_is_end() {
        let tokens: Vec<SourceToken> = vec![
            SourceToken {
                token: Token::Keyword(Keyword::Use),
                span: Span::new(1, 1, 3, 1),
            },
            SourceToken {
                token: Token::End,
                span: Span::new(4, 1, 4, 1),
            },
        ];
        let mut file = File {
            namespace: "Some.Base.Namespace".to_string(),
            uses: HashMap::new(),
            functions: HashMap::new(),
        };

        match parse_use(&mut file, &tokens, &0, &tokens[0]) {
            Ok(_) => panic!("Should not have parsed correctly"),
            Err(failure) => {
                assert_eq!(
                    "At least one identifier must be provided in a use statement.",
                    failure.message,
                );
                assert_eq!(failure.span.split(), (4, 1, 4, 1));
            },
        }
    }

    #[test]
    fn it_will_not_parse_if_an_identifier_is_not_followed_by_a_period_or_end() {
        let mut example_token_sets: Vec<((u16, u16, u16, u16), Vec<SourceToken>)> = vec![
            (
                (3, 3, 3, 3),
                vec![
                    SourceToken {
                        token: Token::Identifier("Foo".to_string()),
                        span: Span::new(2, 2, 2, 2),
                    },
                    SourceToken {
                        token: Token::Keyword(Keyword::Abstract),
                        span: Span::new(3, 3, 3, 3),
                    },
                ],
            ),
            (
                (5, 5, 5, 5),
                vec![
                    SourceToken {
                        token: Token::Identifier("Foo".to_string()),
                        span: Span::new(2, 2, 2, 2),
                    },
                    SourceToken {
                        token: Token::Symbol(Symbol::Period),
                        span: Span::new(3, 3, 3, 3),
                    },
                    SourceToken {
                        token: Token::Identifier("Bar".to_string()),
                        span: Span::new(4, 4, 4, 4),
                    },
                    SourceToken {
                        token: Token::Keyword(Keyword::Abstract),
                        span: Span::new(5, 5, 5, 5),
                    },
                ],
            ),
        ];

        for example_tokens in &mut example_token_sets {
            let mut tokens: Vec<SourceToken> = vec![
                SourceToken {
                    token: Token::Keyword(Keyword::Use),
                    span: Span::new(1, 1, 3, 1),
                },
            ];
            tokens.append(&mut example_tokens.1);

            let mut file = File {
                namespace: "Some.Base.Namespace".to_string(),
                uses: HashMap::new(),
                functions: HashMap::new(),
            };

            match parse_use(&mut file, &tokens, &0, &tokens[0]) {
                Ok(_) => panic!("Should not have parsed correctly"),
                Err(failure) => {
                    assert_eq!(
                        "Expected an identifier.",
                        failure.message,
                    );
                    assert_eq!(failure.span.split(), example_tokens.0);
                },
            }
        }
    }

    #[test]
    fn it_will_parse_correctly_for_one_identifier() {
        let tokens: Vec<SourceToken> = vec![
            SourceToken {
                token: Token::Keyword(Keyword::Use),
                span: Span::new(1, 1, 3, 1),
            },
            SourceToken {
                token: Token::Identifier("Foo".to_string()),
                span: Span::new(2, 2, 2, 2),
            },
            SourceToken {
                token: Token::End,
                span: Span::new(3, 3, 3, 3),
            },
        ];

        let mut file = File {
            namespace: "Some.Base.Namespace".to_string(),
            uses: HashMap::new(),
            functions: HashMap::new(),
        };

        match parse_use(&mut file, &tokens, &0, &tokens[0]) {
            Ok(new_index) => {
                assert_eq!(3, new_index);
                assert!(file.uses.contains_key("Foo"));

                let new_use = &file.uses["Foo"];
                assert_eq!("Foo", new_use.identifier);
                assert_eq!((1, 1, 2, 2), new_use.span.split());
            },
            Err(err) => panic!("Should not have failed to parse: {}", err.message),
        }
    }

    #[test]
    fn it_will_parse_correctly_for_many_identifiers() {
        let tokens: Vec<SourceToken> = vec![
            SourceToken {
                token: Token::Keyword(Keyword::Use),
                span: Span::new(1, 1, 3, 1),
            },
            SourceToken {
                token: Token::Identifier("Foo".to_string()),
                span: Span::new(2, 2, 2, 2),
            },
            SourceToken {
                token: Token::Symbol(Symbol::Period),
                span: Span::new(3, 3, 3, 3),
            },
            SourceToken {
                token: Token::Identifier("Bar".to_string()),
                span: Span::new(4, 4, 4, 4),
            },
            SourceToken {
                token: Token::Symbol(Symbol::Period),
                span: Span::new(5, 5, 5, 5),
            },
            SourceToken {
                token: Token::Identifier("Baz".to_string()),
                span: Span::new(6, 6, 6, 6),
            },
            SourceToken {
                token: Token::End,
                span: Span::new(7, 7, 7, 7),
            },
        ];

        let mut file = File {
            namespace: "Some.Base.Namespace".to_string(),
            uses: HashMap::new(),
            functions: HashMap::new(),
        };

        match parse_use(&mut file, &tokens, &0, &tokens[0]) {
            Ok(new_index) => {
                assert_eq!(7, new_index);
                assert!(file.uses.contains_key("Foo.Bar.Baz"));

                let new_use = &file.uses["Foo.Bar.Baz"];
                assert_eq!("Foo.Bar.Baz", new_use.identifier);
                assert_eq!((1, 1, 6, 6), new_use.span.split());
            },
            Err(err) => panic!("Should not have failed to parse: {}", err.message),
        }
    }
}
