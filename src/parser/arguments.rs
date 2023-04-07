use std::collections::HashMap;
use crate::lexer::{get_next_token, SourceToken};
use crate::lexer::token::{Symbol, Token};
use crate::parser::{Failure, File};
use crate::parser::resolved_type::parse_resolved_type;
use crate::parser::statement::ResolvedType;

/// Parse a set of argument definitions, or none if there aren't any. Consumes the closing parenthesis.
///
/// ## Arguments
/// * `file` - the file context
/// * `tokens` - the whole set of tokens for the file
/// * `starting_index` - the index of the keyword
/// * `starting_token` - the token for the keyword
///
/// ## Returns
/// A [Result] containing the arguments and the new index into the tokens after parsing the
/// arguments, or a failure.
pub fn parse_arguments(
    file: &File,
    tokens: &Vec<SourceToken>,
    starting_index: &usize,
    starting_token: &SourceToken,
) -> Result<(HashMap<String, ResolvedType>, usize), Failure> {
    let mut next_index = starting_index.clone() + 1;
    let mut previous_token: &SourceToken = starting_token;
    let mut arguments: HashMap<String, ResolvedType> = HashMap::new();

    loop {
        let (id_or_bracket, new_previous_token) = get_next_token(
            tokens,
            next_index,
            previous_token,
            |next_token| match next_token.token {
                Token::Identifier(_) => Ok(next_token),
                Token::Symbol(Symbol::ParenClose) => Ok(next_token),
                _ => Err(Some(next_token)) 
            },
            "Expected identifier or closing parenthesis.".to_string(),
        )?;
        next_index += 1;
        previous_token = new_previous_token;

        if let Token::Symbol(Symbol::ParenClose) = &id_or_bracket.token {
            break;
        }

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

        let (resolved_type, new_next_index) = parse_resolved_type(
            &file,
            &tokens,
            &next_index,
            &previous_token,
        )?;
        next_index = new_next_index.clone();
        previous_token = &tokens[next_index - 1];

        // this has to be true at this point
        if let Token::Identifier(id) = &id_or_bracket.token {
            arguments.insert(id.to_string(), resolved_type);
        }

        // try for comma, let start of loop handle close
        let (comma_or_end, new_previous_token) = get_next_token(
            tokens,
            next_index,
            previous_token,
            |next_token| match next_token.token {
                Token::Symbol(Symbol::Comma) => Ok(next_token),
                Token::Symbol(Symbol::ParenClose) => Ok(next_token),
                _ => Err(Some(next_token)),
            },
            "Expected comma or closing parenthesis.".to_string(),
        )?;
        next_index += 1;
        previous_token = new_previous_token;

        if let Token::Symbol(Symbol::ParenClose) = &comma_or_end.token {
            break;
        }
    };

    return Ok((arguments, next_index));
}

