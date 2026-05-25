from odoo import models, fields, exceptions

class JTEmployeesGrades(models.Model):
    _name = 'jtemployees.grades'
    _description = 'Grades for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name = fields.Char(
        string='Name',
        required=True,
        tracking=True
    )
    amount = fields.Float(
        string='Amount',
        required=True,
        tracking=True
    )

