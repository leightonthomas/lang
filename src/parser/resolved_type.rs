use std::collections::HashMap;
use std::rc::Rc;
use crate::lexer::{get_next_token, SourceToken, Span};
use crate::lexer::token::{Keyword, Symbol, Token};
use crate::parser::{Failure, File};
use crate::parser::statement::{as_type_reference, DefinedFunction, Function, ResolvedType, Visibility};

/// Parse a referenced type.
///
/// ## Examples of parseable input
/// * `Foo`
///
/// ## Arguments
/// * `file` - the current file
/// * `tokens` - the whole set of tokens for the file
/// * `starting_index` - the index of the symbol
/// * `starting_token` - the token for the preceding symbol
///
/// ## Returns
/// A [Result] containing the new index into the tokens after parsing the function along with the
/// parsed type, or a failure.
pub fn parse_resolved_type(
    file: &File,
    tokens: &Vec<SourceToken>,
    starting_index: &usize,
    starting_token: &SourceToken,
) -> Result<(ResolvedType, usize), Failure> {
    let mut next_index = starting_index.clone() + 1;
    let mut previous_token: &SourceToken = starting_token;

    let (identifier, new_previous_token) = get_next_token(
        tokens,
        next_index,
        previous_token,
        |next_token| match &next_token.token {
            Token::Identifier(id) => Ok(id),
            _ => Err(Some(next_token)),
        },
        "Expected type identifier.".to_owned(),
    )?;
    next_index += 1;
    previous_token = new_previous_token;

    let unresolved_type = ResolvedType {
        namespace: (&file.uses)
            .get(identifier)
            .map(|u| &u.identifier)
            .unwrap_or(&file.namespace)
            .to_string()
        ,
        identifier: (identifier.to_owned(), Some(previous_token.span.clone())),
    };

    return Ok((unresolved_type, next_index));
}

