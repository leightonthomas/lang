use std::collections::HashMap;
use crate::lexer::{CodeBlock, SourceToken, Span};
use crate::lexer::token::{Keyword, Symbol, Token};
use crate::parser::statement::{Function, ToTypeReference, Use};
use crate::parser::top_level::function::parse_function;
use crate::parser::top_level::uses::parse_use;

pub mod statement;
pub mod top_level;
pub mod resolved_type;
pub mod arguments;
pub mod expression;

#[derive(Debug)]
pub struct File {
    pub namespace: String,
    pub uses: HashMap<String, Use>,
    pub functions: HashMap<String, Function>,
    code_blocks: HashMap<usize, CodeBlock>,
}

#[derive(Debug)]
pub struct Failure {
    pub message: String,
    pub span: Span,
}

/// Convert raw tokens to a structured AST.
///
/// ## Arguments
/// * `tokens` - The file's tokens.
/// * `namespace` - The file's namespace.
pub fn parse_file(
    tokens: &Vec<SourceToken>,
    code_blocks: HashMap<usize, CodeBlock>,
    namespace: &str,
) -> Result<File, Failure> {
    let mut file = File {
        namespace: namespace.to_owned(),
        uses: HashMap::new(),
        functions: HashMap::new(),
        code_blocks,
    };

    // our index into the tokens
    let mut index = 0;
    loop {
        let maybe_token = tokens.get(index);
        if maybe_token.is_none() {
            break;
        }

        let token = maybe_token.unwrap();
        let new_index = match &token.token {
            Token::Keyword(keyword) => match keyword {
                Keyword::Use => parse_use(&mut file, &tokens, &index, &token)?,
                Keyword::Public | Keyword::Function => parse_function(&mut file, &tokens, &index, &token)?,
                _ => return Err(Failure {
                    message: "Invalid keyword, expected one of: use.".to_owned(),
                    span: token.span.clone(),
                }),
            },
            _ => return Err(Failure {
                message: "Expected a use statement, function definition, or comment.".to_owned(),
                span: token.span.clone(),
            }),
        };

        index = new_index;
    }

    return Ok(file);
}
