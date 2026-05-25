from odoo import models, fields, exceptions

class JTEmployeesPointsTemplates(models.Model):
    _name = 'jtemployees.points.templates'
    _description = 'points for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    title = fields.Char(
        string='Title',
        required=True,
        tracking=True
    )
    
    is_hr = fields.Boolean(string='HR Only', required=True, tracking=True)