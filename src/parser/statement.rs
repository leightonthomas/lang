use std::collections::HashMap;
use std::rc::Rc;
use crate::lexer::{Span};

pub trait Block {
    fn expressions(&self) -> &Vec<Expression>;
}

pub trait ToTypeReference {
    fn as_type_reference(&self) -> String;
}

#[derive(Debug)]
pub struct DefinedFunction {
    pub identifier: (String, Span),
    pub visibility: (Visibility, Option<Span>),
    pub arguments: HashMap<String, ResolvedType>,
    pub expressions: Vec<Expression>,
    pub returns: ResolvedType,
}
impl Block for DefinedFunction {
    fn expressions(&self) -> &Vec<Expression> {
        &self.expressions
    }
}

#[derive(Debug)]
pub struct AnonymousFunction {
    arguments: HashMap<String, (Span, ResolvedType)>,
    returns: ResolvedType,
    expressions: Vec<Expression>,
}
impl Block for AnonymousFunction {
    fn expressions(&self) -> &Vec<Expression> {
        &self.expressions
    }
}

#[derive(Debug)]
pub enum Function {
    Regular(DefinedFunction),
    Anonymous(AnonymousFunction),
}

#[derive(Debug)]
pub enum Prefix {
    Not(Box<SubExpression>),
    Minus(Box<SubExpression>),
    Plus(Box<SubExpression>),
    Group(Box<SubExpression>),
}

#[derive(Debug)]
pub enum Infix {
    Add(Box<SubExpression>, Box<SubExpression>),
    Sub(Box<SubExpression>, Box<SubExpression>),
}

#[derive(Debug)]
pub enum SubExpression {
    FunctionCall(String, Vec<SubExpression>),
    StringLiteral(String),
    IntegerLiteral(i64),
    Variable(String),
    Prefix(Prefix),
    Infix(Infix),
}

#[derive(Debug)]
pub enum Expression {
    VariableDeclaration(VariableDeclaration),
    Return(SubExpression),
    SubExpression(SubExpression),
}

#[derive(Debug)]
pub struct VariableDeclaration {
    pub identifier: (String, Span),
    pub variable_type: ResolvedType,
    pub value: SubExpression,
}

#[derive(Debug)]
pub enum Visibility {
    Public,
    Private,
    Protected,
}

#[derive(Debug)]
pub struct ResolvedType {
    pub(crate) namespace: String,
    /// Static identifier for the type. Note that this does not include the namespace prefix.
    /// The token span will only be present if this was resolved by a user before compilation.
    pub(crate) identifier: (String, Option<Span>),
}
impl ToTypeReference for ResolvedType {
    fn as_type_reference(&self) -> String {
        as_type_reference(&self.namespace, &self.identifier.0)
    }
}

#[derive(Debug)]
pub struct Use {
    pub identifier: String,
    pub span: Span,
}
impl ToTypeReference for Use {
    fn as_type_reference(&self) -> String {
        self.identifier.clone()
    }
}

#[inline(always)]
pub fn as_type_reference(namespace: &str, identifier: &str) -> String {
    format!("{}.{}", namespace, identifier)
}
impl ToTypeReference for Vec<&str> {
    fn as_type_reference(&self) -> String {
        self.join(".").to_owned()
    }
}
