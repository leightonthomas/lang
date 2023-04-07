use std::collections::HashMap;
use crate::lexer::{get_next_token, SourceToken};
use crate::lexer::token::{Keyword, Symbol, Token};
use crate::parser::{Failure, File};
use crate::parser::expression::sub_expression::parse_sub_expression;
use crate::parser::resolved_type::parse_resolved_type;
use crate::parser::statement::{as_type_reference, ResolvedType, SubExpression, VariableDeclaration};

use super::sub_expression::Precedence;

/// Parse a variable declaration, assuming the "let" keyword has already been parsed.
///
/// ## Arguments
/// * `let_token` - the token for the "let" keyword
/// * `file` - the file context
/// * `tokens` - the whole set of tokens for the file
/// * `starting_index` - the index of the keyword
/// * `starting_token` - the token for the keyword
///
/// ## Returns
/// A [Result] containing the declaration and the new index into the tokens, or a failure.
pub fn parse_variable_declaration(
    file: &File,
    tokens: &Vec<SourceToken>,
    starting_index: &usize,
    let_token: &SourceToken,
) -> Result<(VariableDeclaration, usize), Failure> {
    let mut next_index = starting_index.clone() + 1;
    let mut previous_token: &SourceToken = let_token;

    let (identifier, new_previous_token) = get_next_token(
        tokens,
        next_index,
        previous_token,
        |next_token| match &next_token.token {
            Token::Identifier(id) => Ok((id, next_token.span.clone())),
            _ => Err(Some(next_token)),
        },
        "Expected variable identifier.".to_owned(),
    )?;
    next_index += 1;
    previous_token = new_previous_token;

    let (_, new_previous_token) = get_next_token(
        tokens,
        next_index,
        previous_token,
        |next_token| match next_token.token {
            Token::Symbol(Symbol::Colon) => Ok(next_token),
            _ => Err(Some(next_token)),
        },
        "Expected colon.".to_string(),
    )?;
    previous_token = new_previous_token;

    let (var_type, new_next_index) = parse_resolved_type(&file, tokens, &mut next_index, previous_token)?;
    next_index = new_next_index;
    previous_token = &tokens[next_index - 1];

    let (_, new_previous_token) = get_next_token(
        tokens,
        next_index,
        previous_token,
        |next_token| match next_token.token {
            Token::Symbol(Symbol::Equal) => Ok(next_token),
            _ => Err(Some(next_token)),
        },
        "Expected equal symbol.".to_string(),
    )?;
    previous_token = new_previous_token;

    let (value, new_next_index) = parse_sub_expression(&file, tokens, &mut next_index, previous_token, Precedence::Default)?;

    next_index = new_next_index;
    previous_token = &tokens[next_index - 1];

    let variable_declaration = VariableDeclaration {
        identifier: (identifier.0.to_string(), identifier.1),
        variable_type: var_type,
        value,
    };

    return Ok((variable_declaration, next_index));
}

