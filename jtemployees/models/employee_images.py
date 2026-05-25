from odoo import models, fields, exceptions

class JTEmployeesImages(models.Model):
    _name = 'jtemployees.images'
    _description = 'images for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name        = fields.Char(string='Document Name', required=True, tracking=True)
    image       = fields.Image(string='Image')
    employee_id = fields.Many2one('hr.employee', string='Employee', required=True, tracking=True)
    deleted     = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    def write(self, vals):
        if 'image' in vals:
            raise exceptions.ValidationError("Modifying documents is not allowed, instead delete the image and upload a newer one.")
        return super().write(vals)
    
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
